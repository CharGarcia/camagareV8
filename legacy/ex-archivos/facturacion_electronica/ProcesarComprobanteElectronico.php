<?php
ini_set('default_socket_timeout', 600);
ini_set("soap.wsdl_cache_enabled", "0");
class factura extends comprobanteGeneral
{

    public $detalles; // detalleFactura
    public $guiaRemision; // string
    public $identificacionComprador; // string
    public $importeTotal; // string
    public $infoAdicional; // campoAdicional
    public $moneda; // string
    public $propina; // string
    public $razonSocialComprador; // string 
    public $tipoIdentificacionComprador; // string
    public $totalConImpuesto; // totalImpuesto
    public $totalDescuento; // string
    public $totalSinImpuestos; // string
    public $pagos;
    public $direccionComprador; //string
    public $otrosRubros;
}

class proforma
{

    public $configCorreo;
    public $dirLogo;
    public $dirProformas;
    public $tipoEmision;
    public $razonSocial;
    public $nombreComercial;
    public $ruc;
    public $numero;
    public $dirMatriz;
    public $dirEstablecimiento;
    public $fechaEmision;
    public $razonSocialComprador;
    public $identificacionComprador;
    public $direccionComprador;
    public $subTotal12;
    public $subTotal0;
    public $subTotalSinImpuesto;
    public $iva;
    public $totalDescuento;
    public $importeTotal;
    public $detalles; // detalleProforma
    public $infoAdicional;
}

class detalleProforma
{

    public $codigo;
    public $descripcion;
    public $cantidad;
    public $precioUnitario;
    public $descuento;
    public $precioTotalSinImpuesto;
}

class pagos
{

    public $formaPago;
    public $total;
    public $plazo;
    public $unidadTiempo;
}

class rubro
{
    public $concepto;
    public $total;
}

class comprobanteGeneral
{

    public $ambiente; // string
    public $claveAcc; // string
    public $codDoc; // string
    public $configAplicacion; // configAplicacion
    public $configCorreo; // configCorreo
    public $contribuyenteEspecial; // string
    public $dirEstablecimiento; // string
    public $dirMatriz; // string
    public $regimenRIMPE; // string
    public $regimenRIMPENP; // string
    public $agenteRetencion; // string
    public $establecimiento; // string
    public $fechaEmision; // string
    public $nombreComercial; // string
    public $obligadoContabilidad; // string
    public $ptoEmision; // string
    public $razonSocial; // string
    public $ruc; // string
    public $secuencial; // string
    public $tipoDoc; // string
    public $tipoEmision; // string

}

class liquidacionCompra extends comprobanteGeneral
{

    public $detalles; // detalleLiquidacionCompra
    public $direccionProveedor; // string
    public $identificacionProveedor; // string
    public $importeTotal; // string
    public $infoAdicional; // campoAdicional
    public $moneda; // string
    public $razonSocialProveedor; // string
    public $tipoIdentificacionProveedor; // string
    public $totalConImpuesto; // totalImpuesto
    public $totalDescuento; // string
    public $totalSinImpuestos; // string
    public $pagos;
}

class detalleFactura
{

    public $cantidad; // string
    public $codigoAuxiliar; // string
    public $codigoPrincipal; // string
    public $descripcion; // string
    public $descuento; // string
    public $detalleAdicional; // detalleAdicional
    public $impuestos; // impuesto
    public $precioTotalSinImpuesto; // string
    public $precioUnitario; // string

}

class detalleLiquidacionCompra
{

    public $cantidad; // string
    public $codigoAuxiliar; // string
    public $codigoPrincipal; // string
    public $descripcion; // string
    public $descuento; // string
    public $detalleAdicional; // detalleAdicional
    public $impuestos; // impuesto
    public $precioTotalSinImpuesto; // string
    public $precioUnitario; // string

}

class detalleAdicional
{

    public $nombre; // string
    public $valor; // string

}

class impuesto
{

    public $baseImponible; // string
    public $codigo; // string
    public $codigoPorcentaje; // string
    public $tarifa; // string
    public $valor; // string

}

class campoAdicional
{

    public $nombre; // string
    public $valor; // string

}

class totalImpuesto
{

    public $baseImponible; // string
    public $codigo; // string
    public $codigoPorcentaje; // string
    public $descuentoAdicional; // string
    public $tarifa; // string
    public $valor; // string

}

class configAplicacion
{

    public $dirAutorizados; // string
    public $dirFirma; // string
    public $dirLogo; // string
    public $passFirma; // string

}

class configCorreo
{

    public $correoAsunto; // string
    public $correoHost; // string
    public $correoPass; // string
    public $correoPort; // string
    public $correoRemitente; // string
    public $correoEmpresa; // string
    public $sslHabilitado; // boolean

}

class guiaRemision extends comprobanteGeneral
{

    public $destinatarios; // destinatario
    public $dirPartida; // string
    public $fechaFinTransporte; // string
    public $fechaIniTransporte; // string
    public $infoAdicional; // campoAdicional
    public $placa; // string
    public $razonSocialTransportista; // string
    public $rise; // string
    public $rucTransportista; // string
    public $tipoIdentificacionTransportista; // string

}

class destinatario
{

    public $codDocSustento; // string
    public $codEstabDestino; // string
    public $detalles; // detalleGuiaRemision
    public $dirDestinatario; // string
    public $docAduaneroUnico; // string
    public $fechaEmisionDocSustento; // string
    public $identificacionDestinatario; // string
    public $motivoTraslado; // string
    public $numAutDocSustento; // string
    public $numDocSustento; // string
    public $razonSocialDestinatario; // string
    public $ruta; // string

}

class detalleGuiaRemision
{

    public $cantidad; // string
    public $codigoAdicional; // string
    public $codigoInterno; // string
    public $descripcion; // string
    public $detallesAdicionales; // detalleAdicional

}

class comprobanteRetencion extends comprobanteGeneral
{

    public $identificacionSujetoRetenido; // string
    public $impuestos; // impuestoComprobanteRetencion
    public $infoAdicional; // campoAdicional
    public $periodoFiscal; // string
    public $razonSocialSujetoRetenido; // string
    public $tipoIdentificacionSujetoRetenido; // string

}

class impuestoComprobanteRetencion
{

    public $baseImponible; // string
    public $codDocSustento; // string
    public $codigo; // string
    public $codigoRetencion; // string
    public $fechaEmisionDocSustento; // string
    public $numDocSustento; // string
    public $porcentajeRetener; // string
    public $valorRetenido; // string

}

class notaDebito extends comprobanteGeneral
{

    public $codDocModificado; // string
    public $fechaEmisionDocSustento; // string
    public $identificacionComprador; // string
    public $impuestos; // impuesto
    public $infoAdicional; // campoAdicional
    public $motivos; // motivo
    public $numDocModificado; // string
    public $razonSocialComprador; // string
    public $rise; // string
    public $tipoIdentificacionComprador; // string
    public $totalSinImpuestos; // string
    public $valorTotal; // string
    public $pagos;
}

class motivo
{

    public $razon; // string
    public $valor; // string

}

class notaCredito extends comprobanteGeneral
{

    public $codDocModificado; // string
    public $detalles; // detalleNotaCredito
    public $fechaEmisionDocSustento; // string
    public $identificacionComprador; // string
    public $infoAdicional; // campoAdicional
    public $moneda; // string
    public $motivo; // string
    public $numDocModificado; // string
    public $razonSocialComprador; // string
    public $rise; // string
    public $tipoIdentificacionComprador; // string
    public $totalConImpuesto; // totalImpuesto
    public $totalSinImpuestos; // string
    public $valorModificacion; // string

}

class detalleNotaCredito
{

    public $cantidad; // string
    public $codigoAdicional; // string
    public $codigoInterno; // string
    public $descripcion; // string
    public $descuento; // string
    public $detallesAdicionales; // detalleAdicional
    public $impuestos; // impuesto
    public $precioTotalSinImpuesto; // string
    public $precioUnitario; // string

}

class comprobantePendiente
{

    public $ambiente; // string
    public $codDoc; // string
    public $configAplicacion; // configAplicacion
    public $configCorreo; // configCorreo
    public $establecimiento; // string
    public $fechaEmision; // string
    public $ptoEmision; // string
    public $ruc; // string
    public $secuencial; // string
    public $tipoEmision; // string
    public $clavAcc;
}

class mensajeGenerado
{

    public $identificador; // string
    public $informacionAdicional; // string
    public $mensaje; // string
    public $tipo; // string

}

class respuesta
{

    public $claveAcceso; // string
    public $comprobanteID; // string
    public $estadoComprobante; // string
    public $mensajes; // mensajeGenerado
    public $numeroAutorizacion; // string
    public $fechaAutorizacion;
}

class procesarComprobantePendiente
{

    public $comprobantePendiente; // comprobantePendiente

}

class procesarComprobantePendienteResponse
{

    public $return; // respuesta

}

class procesarXML
{

    public $configAplicacion; // configAplicacion
    public $configCorreo; // configCorreo
    public $xml; //string

}

class procesarXMLResponse
{

    public $return; // respuesta

}

class procesarComprobante
{

    public $comprobante; // comprobanteGeneral
    public $envioSRI;
}

class procesarComprobanteResponse
{

    public $return; // respuesta

}

class generarXMLPDF
{

    public $comprobante; // comprobanteGeneral
    public $envioEmail;
}

class generarXMLPDFResponse
{

    public $return; // respuesta

}

class procesarProforma
{

    public $proforma; // proforma

}

class procesarProformaResponse
{

    public $return; // respuesta

}

/**
 * ProcesarComprobanteElectronico class
 * 
 *  
 * 
 * @author    {author}
 * @copyright {copyright}
 * @package   {package}
 */
class ProcesarComprobanteElectronico extends SoapClient
{

    private static $classmap = array(
        'factura' => 'factura',
        'proforma' => 'proforma',
        'liquidacionCompra' => 'liquidacionCompra',
        'comprobanteGeneral' => 'comprobanteGeneral',
        'detalleFactura' => 'detalleFactura',
        'detalleProforma' => 'detalleProforma',
        'detalleLiquidacionCompra' => 'detalleLiquidacionCompra',
        'detalleAdicional' => 'detalleAdicional',
        'impuesto' => 'impuesto',
        'campoAdicional' => 'campoAdicional',
        'totalImpuesto' => 'totalImpuesto',
        'configAplicacion' => 'configAplicacion',
        'configCorreo' => 'configCorreo',
        'guiaRemision' => 'guiaRemision',
        'destinatario' => 'destinatario',
        'detalleGuiaRemision' => 'detalleGuiaRemision',
        'comprobanteRetencion' => 'comprobanteRetencion',
        'impuestoComprobanteRetencion' => 'impuestoComprobanteRetencion',
        'notaDebito' => 'notaDebito',
        'motivo' => 'motivo',
        'pagos' => 'pagos',
        'otrosRubros' => 'otrosRubros',
        'notaCredito' => 'notaCredito',
        'detalleNotaCredito' => 'detalleNotaCredito',
        'comprobantePendiente' => 'comprobantePendiente',
        'mensajeGenerado' => 'mensajeGenerado',
        'respuesta' => 'respuesta',
        'procesarComprobantePendiente' => 'procesarComprobantePendiente',
        'procesarComprobantePendienteResponse' => 'procesarComprobantePendienteResponse',
        'procesarComprobante' => 'procesarComprobante',
        'procesarComprobanteResponse' => 'procesarComprobanteResponse',
        'generarXMLPDF' => 'generarXMLPDF',
        'generarXMLPDFResponse' => 'generarXMLPDFResponse',
        'procesarXML' => 'procesarXML',
        'procesarXMLResponse' => 'procesarXMLResponse',
    );
    //http://64.225.69.65:8080/MasterOffline/ProcesarComprobanteElectronico?wsdl 
    //public function ProcesarComprobanteElectronico($wsdl = "http://localhost:8080/MasterOffline/ProcesarComprobanteElectronico?wsdl", $options = array()) {
    public function ProcesarComprobanteElectronico($wsdl = "http://64.225.69.65:8080/MasterOffline/ProcesarComprobanteElectronico?wsdl", $options = array())
    {
        foreach (self::$classmap as $key => $value) {
            if (!isset($options['classmap'][$key])) {
                $options['classmap'][$key] = $value;
            }
        }
        parent::__construct($wsdl, $options);
    }

    /**
     *  
     *
     * @param procesarComprobantePendiente $parameters
     * @return procesarComprobantePendienteResponse
     */
    public function procesarComprobantePendiente(procesarComprobantePendiente $parameters)
    {
        return $this->__soapCall(
            'procesarComprobantePendiente',
            array($parameters),
            array(
                'uri' => 'http://Servicio/',
                'soapaction' => ''
            )
        );
    }

    /**
     *  
     *
     * @param procesarComprobante $parameters
     * @return procesarComprobanteResponse
     */
    public function procesarComprobante(procesarComprobante $parameters)
    {
        return $this->__soapCall(
            'procesarComprobante',
            array($parameters),
            array(
                'uri' => 'http://Servicio/',
                'soapaction' => ''
            )
        );
    }

    /**
     *  
     *
     * @param generarXMLPDF $parameters
     * @return generarXMLPDFResponse
     */
    public function generarXMLPDF(generarXMLPDF $parameters)
    {
        return $this->__soapCall(
            'generarXMLPDF',
            array($parameters),
            array(
                'uri' => 'http://Servicio/',
                'soapaction' => ''
            )
        );
    }

    /**
     *  
     *
     * @param procesarXML $parameters
     * @return procesarXMLResponse
     */
    public function procesarXML(procesarXML $parameters)
    {
        return $this->__soapCall(
            'procesarXML',
            array($parameters),
            array(
                'uri' => 'http://Servicio/',
                'soapaction' => ''
            )
        );
    }

    /**
     *  
     *
     * @param procesarXML $parameters
     * @return procesarXMLResponse
     */
    public function procesarProforma(procesarProforma $parameters)
    {
        return $this->__soapCall(
            'procesarProforma',
            array($parameters),
            array(
                'uri' => 'http://Servicio/',
                'soapaction' => ''
            )
        );
    }
}

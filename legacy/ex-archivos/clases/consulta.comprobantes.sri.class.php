<?php

/**
 * PHP Version 5
 * @package ConsultaCompSri
 * @link https://dataplus-security.com The ConsultaCompSri project
 * @author Bryan Alejandro Torres Castillo (Desarrollador Principal) <torresbryan17@hotmail.com>
 * @copyright 2020 - 2025 Bryan Torres
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * ConsultaCompSri - Permite la consulta de comprobantes en los servidores del SRI por clave de acceso.
 *    Ejemplo de uso:
 *
 *        $consulta = new ConsultaCompSri();
 *        $consulta->claveacceso = '1611201901099001121400120180080001727650000000210';
 *
 *        $comp = $consulta->consultar(); // Devuelve cualquier comprobante
 *        //$comp = $consulta->consultarFactura(); // Devueve solo si es factura
 *        if ($comp) {
 *            print_r($comp);
 *            print_r($consulta->getNroDocumento());
 *            print_r($consulta->getInfoAdicional());
 *            print_r($consulta->getEmail());
 *            print_r($consulta->getNroTelefono());
 *        }else{
 *            if (count($consulta->get_errores())>0) {
 *                foreach ($consulta->get_errores() as $err) {
 *                    echo $err . "<br>";
 *                }
 *            }
 *        }
 *        
 * @package ConsultaCompSri
 * @author Bryan Alejandro Torres Castillo (Desarrollador Principal) <torresbryan17@hotmail.com>
 */
class ConsultaCompSri
{

    /**
     * URL WebServices SRI permite la consulta
     * de los comprobantes emitidos en ambiente
     * de PRUEBAS.
     * @var string
     */
    public $url_ws_pruebas = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    /**
     * URL WebServices SRI permite la consulta
     * de los comprobantes emitidos en ambiente
     * de PRODUCCIÓN.
     * @var string
     */
    public $url_ws_produccion = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

    /**
     * Clave de acceso del comprobante
     * que desea consultar
     * @var string
     */
    public $claveacceso;

    /**
     * Diferencia el ambiente
     * Este proceso lo hace automaticamente
     * mediante la variable $claveacceso
     * @var int
     */
    private $ambiente;

    /**
     * Almacena el url del servicio
     * correcto al que tiene que apuntar
     * la consulta
     * @var string
     */
    private $servicio;

    /**
     * Almacena los posibles errores generados
     * en el proceso de consulta al SRI.
     * @var array
     */
    private static $errores = [];

    /**
     * Poner en TRUE solo cuando una requiera
     * mostrar todos el listado de errores posible
     */
    private $debug = true;

    public function __construct($data = false)
    {
    }

    private function ini()
    {

        if (!empty($this->claveacceso)) {

            if (strlen($this->claveacceso) == 49) {

                $this->ambiente = substr($this->claveacceso, 23, -25);

                if ($this->ambiente == 1) {
                    $this->servicio = $this->url_ws_pruebas;
                } else {
                    $this->servicio = $this->url_ws_produccion;
                }

                return $this->testConexion();
            } else {
                $this->new_error('Error en clave de acceso: ' . $this->claveacceso);
                return false;
            }
        } else {
            $this->new_error('Error en clave de acceso');
            return false;
        }
    }

    /**
     * Agrega un error al listado de errores
     * @param string
     */
    private function new_error($msg = '')
    {
        self::$errores[] = $msg;
    }

    /**
     * Varifica la conexion con los servidores del SRI.
     */
    private function testConexion()
    {
        try {
            $client = new SoapClient($this->servicio);
            return true;
        } catch (SoapFault $fault) {
            if ($this->debug) {
                trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
            } else {
                $this->new_error("El servicio se encuentra temporalmente fuera de línea.");
                return false;
            }
        }

        return false;
    }

    /**
     * Devuelve el comprobante consultado a los servidores del SRI.
     * @return object | false
     */
    public function consultar()
    {
        if ($this->ini()) {
            $parameters = array('claveAccesoComprobante' => (string) $this->claveacceso);

            try {

                if (!is_null($this->servicio)) {
                    $client = new SoapClient($this->servicio);

                    $repuestaSriXml = $client->autorizacionComprobante($parameters);

                    if (isset($repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado)) {

                        if ($repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado == 'AUTORIZADO') {

                            $string_xml = $repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->comprobante;

                            if (!empty($string_xml)) {

                                return simplexml_load_string($string_xml);
                            }
                        }
                    }
                } else
                    $this->new_error('Fue imposible consultar el comprobante electrónico.');
            } catch (SoapFault $fault) {

                if ($this->debug) {
                    trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
                } else {
                    $this->new_error("El servicio se encuentra temporalmente fuera de línea.");
                    return false;
                }
            }
        }

        return false;
    }


    public function consultar_estado()
    {
        if ($this->ini()) {
            $parameters = array('claveAccesoComprobante' => (string) $this->claveacceso);

            try {

                if (!is_null($this->servicio)) {
                    $client = new SoapClient($this->servicio);

                    $repuestaSriXml = $client->autorizacionComprobante($parameters);

                    if (isset($repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado)) {

                        return $repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado;
                    }
                } else
                    $this->new_error('Fue imposible consultar el comprobante electrónico.');
            } catch (SoapFault $fault) {

                if ($this->debug) {
                    trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
                } else {
                    $this->new_error("El servicio se encuentra temporalmente fuera de línea.");
                    return false;
                }
            }
        }
        return false;
    }


    public function consultar_fecha_autorizacion()
    {
        if ($this->ini()) {
            $parameters = array('claveAccesoComprobante' => (string) $this->claveacceso);

            try {

                if (!is_null($this->servicio)) {
                    $client = new SoapClient($this->servicio);

                    $repuestaSriXml = $client->autorizacionComprobante($parameters);

                    if (isset($repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->estado)) {

                        return $repuestaSriXml->RespuestaAutorizacionComprobante->autorizaciones->autorizacion->fechaAutorizacion;
                    }
                } else
                    $this->new_error('Fue imposible consultar el comprobante electrónico.');
            } catch (SoapFault $fault) {

                if ($this->debug) {
                    trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
                } else {
                    $this->new_error("El servicio se encuentra temporalmente fuera de línea.");
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Devuelve solo si es una factura electrónica .
     * @return object | false
     */
    public function consultarFactura()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '01') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve solo si es una Liquidacion de compra electrónica .
     * @return object | false
     */
    public function consultarLiquidacionCompra()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '03') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve solo si es una Nota de Crédito electrónica .
     * @return object | false
     */
    public function consultarNotaCredito()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '04') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve solo si es una Nota de Débito electrónica .
     * @return object | false
     */
    public function consultarNotaDebito()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '05') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve solo si es una Guia de Remisión electrónica .
     * @return object | false
     */
    public function consultarGuiaRemision()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '06') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve solo si es una Retención electrónica .
     * @return object | false
     */
    public function consultarRetencion()
    {
        $comp = $this->consultar();
        if ($comp) {
            if ((string) $comp->infoTributaria->codDoc == '07') {
                return $comp;
            }
        }

        return false;
    }

    /**
     * Devuelve el numero de comprobante.
     * @return object | false
     */
    public function getNroDocumento()
    {
        $comp = $this->consultar();
        if ($comp) {
            $estab = (string) $comp->infoTributaria->estab;
            $ptoEmi = (string) $comp->infoTributaria->ptoEmi;
            $secuencial = (string) $comp->infoTributaria->secuencial;
            return $estab . '-' . $ptoEmi . '-' . $secuencial;
        }

        return false;
    }

    /**
     * Devuelve el numero de comprobante.
     * @return object | false
     */
    public function getInfoAdicional()
    {
        $listInfoAd = [];
        $comp = $this->consultar();
        if ($comp) {
            if (count($comp->infoAdicional->campoAdicional) > 0) {
                $i = 0;
                foreach ($comp->infoAdicional->campoAdicional as $attr) {

                    ${"infoAd" . $i} = (string) $attr->attributes();
                    ${"valueInfoAd" . $i} = (string) $attr[0];

                    $listInfoAd[${"infoAd" . $i}] = ${"valueInfoAd" . $i};

                    $i++;
                }
            }
        }

        return $listInfoAd;
    }

    public function getEmail()
    {
        if (count($this->getInfoAdicional()) > 0) {
            foreach ($this->getInfoAdicional() as $value) {
                if ($this->is_valid_email($value)) {
                    return strtolower($value);
                }
            }
        }

        return '';
    }

    public function getNroTelefono()
    {
        if (count($this->getInfoAdicional()) > 0) {
            foreach ($this->getInfoAdicional() as $value) {
                if ($this->is_telefono($value)) {
                    return strtolower(trim($value));
                }
            }
        }

        return '';
    }

    /**
     *
     * Valida un email usando expresiones regulares. 
     *  Devuelve true si es correcto o false en caso contrario
     *
     * @param    string  $str el numero a validar
     * @return   boolean
     *
     */
    private function is_valid_email($str)
    {
        $matches = null;
        return (1 === preg_match('/^[A-z0-9\\._-]+@[A-z0-9][A-z0-9-]*(\\.[A-z0-9_-]+)*\\.([A-z]{2,6})$/', $str, $matches));
    }

    /**
     *
     * Valida un numero de telefono usando expresiones regulares. 
     *  Devuelve true si es correcto o false en caso contrario
     *
     * @param    string  $str la dirección a validar
     * @return   boolean
     *
     */
    private function is_telefono($str)
    {
        $matches = null;
        return (1 === preg_match('/^[+#*\(\)\[\]]*([0-9][ ext+-pw#*\(\)\[\]]*){6,45}$/', $str, $matches));
    }

    /**
     * Devuelve el listado de erorres a mostrar al usuario
     * @return array
     */
    public function get_errores()
    {
        return self::$errores;
    }
}

<?php
include("../ajax/imprime_documento.php");

$con = conenta_login();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'enviar_whatsapp') {
    $id_documento = $_GET['id_documento_whatsapp'];
    $tipo_documento = $_GET['tipo_documento_whatsapp'];
    $mensaje = strClean($_GET['mensaje']);

    if (strlen($_GET['whatsapp_receptor']) == 10) {
        $whatsapp_receptor = "593" . ltrim($_GET['whatsapp_receptor'], '0');

        if ($tipo_documento == 'factura') {
            enviar_factura($con, $id_documento, $whatsapp_receptor, $mensaje);
        }
        if ($tipo_documento == 'cxc') {
            enviar_mensaje($con, $id_documento, $whatsapp_receptor, $mensaje);
        }
        //para enviar las cuentas bancarias
        if ($tipo_documento == 'cb') {
            enviar_mensaje($con, $id_documento, $whatsapp_receptor, $mensaje);
        }
    } else {
        echo  "<div class='alert alert-danger' role='alert'>El número de teléfono no es correcto</div><br>";
    }
}

function enviar_mensaje($con, $id_documento, $whatsapp_receptor, $mensaje)
{
    $query_usuarios_asignados = mysqli_query($con, "SELECT puerto, status FROM puerto_whatsapp WHERE ruc_empresa='" . $id_documento . "' ");
    $row_datos = mysqli_fetch_array($query_usuarios_asignados);
    $puerto = $row_datos['puerto'];
    $status = $row_datos['status'];
    if (isset($puerto) && $status == 1) {
        $whatsapp = new whatsapp();
        $respuesta_mensaje_whatsapp = $whatsapp->enviar_mensaje_whatsapp($row_datos['puerto'], $whatsapp_receptor, $mensaje);
        if ($respuesta_mensaje_whatsapp['success']) {
            echo  "<div class='alert alert-success' role='alert'><span class='glyphicon glyphicon-ok'></span> Mensaje enviado</div><br>";
        } else {
            print_r($respuesta_mensaje_whatsapp);
        }
    } else {
        echo  "<div class='alert alert-danger' role='alert'>No dispone del servicio de whatsapp, contactar a 0958924831 para más información</div><br>";
    }
}

function enviar_factura($con, $id_documento, $whatsapp_receptor, $mensaje)
{
    $whatsapp = new whatsapp();
    $carpeta_ftp = new imprime_documentos();
    $query_documento = mysqli_query($con, "SELECT fac.ruc_empresa as ruc_empresa, 
    fac.serie_factura as serie, fac.secuencial_factura as secuencial, 
    cli.ruc as ruc_cliente, emp.nombre as empresa 
    FROM encabezado_factura as fac 
    INNER JOIN clientes as cli ON cli.id=fac.id_cliente 
    INNER JOIN empresas as emp On emp.ruc=fac.ruc_empresa
    WHERE fac.id_encabezado_factura='" . $id_documento . "' ");
    $row_datos = mysqli_fetch_array($query_documento);
    $ruc_empresa = $row_datos['ruc_empresa'];
    //$empresa = $row_datos['empresa'];
    $serie = $row_datos['serie'];
    $secuencial = $row_datos['secuencial'];
    $ruc_cliente = $row_datos['ruc_cliente'];

    $numero_factura = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
    $archivo_pdf = $carpeta_ftp->copia_documento_tmp("internet", 'facturas_autorizadas', $ruc_empresa, $ruc_cliente, 'FAC', $serie, $secuencial, '.pdf');
    $query_usuarios_asignados = mysqli_query($con, "SELECT puerto, status FROM puerto_whatsapp WHERE ruc_empresa='" . $ruc_empresa . "' ");
    $row_datos = mysqli_fetch_array($query_usuarios_asignados);
    $puerto = $row_datos['puerto'];
    $status = $row_datos['status'];

    if (isset($puerto) && $status == 1) {
        $respuest_enviar_archivo_whatsapp = $whatsapp->enviar_archivo_whatsapp($puerto, $whatsapp_receptor, $mensaje, $archivo_pdf);

        if ($respuest_enviar_archivo_whatsapp['success']) {
            echo  "<div class='alert alert-success' role='alert'><span class='glyphicon glyphicon-ok'></span> Factura enviada </div><br>";
        } else {
            print_r($respuest_enviar_archivo_whatsapp);
        }
    } else {
        echo  "<div class='alert alert-danger' role='alert'>No dispone del servicio de whatsapp, contactar a 0958924831 para más información</div><br>";
    }
    unlink("../clases/" . $ruc_cliente . "FAC" . $numero_factura . ".pdf");
}



if ($action == 'generar_qr_whatsapp') {
    $ruc_empresa = $_GET['ruc_empresa_whatsapp'];
    $query_usuarios_asignados = mysqli_query($con, "SELECT puerto, status FROM puerto_whatsapp WHERE ruc_empresa='" . $ruc_empresa . "' ");
    $row_datos = mysqli_fetch_array($query_usuarios_asignados);
    $puerto = $row_datos['puerto'];
    $status = $row_datos['status'];
    if (isset($puerto) && $status == 1) {
        $whatsapp = new whatsapp();
        $respuesta = $whatsapp->conexion_whatsapp($puerto);
        if (!is_array($respuesta)) {
            $mensaje = "Servicio sin activar, contactar con el administrador al 0958924831.";
        } else {
            if (isset($respuesta['qr'])) {
                $qr = '<img src="' . htmlspecialchars($respuesta['qr']) . '" alt="Código QR" style="max-width: 200px; max-height: 200px;">';
                $mensaje = "Para activar el servicio, abrir la app de whatsapp y vincular mediante el QR que se muestra aquí. Esto solo debe hacerlo para iniciar, reactivar o cambiar el número de teléfono emisor.";
            }

            if (isset($respuesta['message'])) {
                $mensaje = $respuesta['message'];
            }
        }
    }

    if (isset($puerto) && $status == 2) {
        $mensaje = "Servicio sin activar, contactar con el administrador al 0958924831.";
    }

    $data = array(
        'status' => $row_datos['status'],
        'qr' =>  isset($qr) ? $qr : "",
        'mensaje' =>  $mensaje
    );

    if ($query_usuarios_asignados) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => $respuesta['message']);
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}

if ($action == 'desactivar_whatsapp') {
    $ruc_empresa = $_GET['ruc_empresa_whatsapp'];
    $query_puerto = mysqli_query($con, "SELECT puerto, status FROM puerto_whatsapp WHERE ruc_empresa='" . $ruc_empresa . "' ");
    $row_datos = mysqli_fetch_array($query_puerto);
    $puerto = $row_datos['puerto'];
    $status = $row_datos['status'];
    if (isset($puerto) && $status == 1) { //significa que esta conectado
        $whatsapp = new whatsapp();
        $respuesta = $whatsapp->desconexion_whatsapp($puerto);
        // Si la API devuelve un error en data.error, manejamos el status
        if (isset($respuesta)) {
            $data = array(
                "status" => false,
                "mensaje" => $respuesta['error'] . " " . isset($respuesta['details']) ? $respuesta['details'] : ""
            );
            $arrResponse = array("status" => true, "data" => $data);
        } else {
            $arrResponse = array("status" => false, "msg" => 'Error en la respuesta del servidor.');
        }
    } else {
        // Si `json_decode()` falla o la respuesta es inválida
        $arrResponse = array("status" => false, "msg" => 'Error en la respuesta del servidor.');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}


class whatsapp
{

    //Retorna Imagen qr en base64
    public function conexion_whatsapp($puerto)
    {

        //get
        header('Content-Type: application/json'); // Asegurar que la salida sea JSON
        $url = "http://137.184.159.242:" . $puerto . "/api/whatsapp-qr";
        $options = [
            "http" => [
                "timeout" => 5 // Espera hasta 5 segundos antes de dar error
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response === false || empty($response)) {
            return json_encode(["message" => "La conexión a whatsapp no está configurada o no responde."]);
        } else {
            // Intentar decodificar JSON
            $data = json_decode($response, true);
            return $data;
        }
    }

    //cerra sesion
    public function desconexion_whatsapp($puerto)
    {
        header('Content-Type: application/json'); // Asegurar que la salida sea JSON
        $url = "http://137.184.159.242:" . $puerto . "/api/whatsapp-close";

        if (!function_exists('curl_init')) {
            return json_encode(array("status" => false, "msg" => "Error: cURL no está habilitado en el servidor."));
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $responseData = json_decode($response, true);
        curl_close($ch);
        return $responseData;
    }

    //Envío de Mensaje
    public function enviar_mensaje_whatsapp($puerto, $telefono, $mensaje)
    {
        //3604 inicial
        $url = "http://137.184.159.242:" . $puerto . "/api/whatsapp-message";

        $data = array_map('strval', array(
            "number" => $telefono,
            "message" =>  $mensaje
        ));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error en la petición: ' . curl_error($ch);
        } else {
            // Decodificar la respuesta JSON
            $responseData = json_decode($response, true);
            return $responseData;
        }
        curl_close($ch);
    }

    /* public function enviar_mensaje_whatsapp($puerto, $telefono, $mensaje, $token = null)
    {
        $base = "http://137.184.159.242:" . intval($puerto) . "/api/whatsapp-message";
        $e164 = $this->to_e164($telefono, '593');
        $jid  = $e164 . '@s.whatsapp.net';

        // Variantes comunes de payload (muchos gateways difieren en nombres de campos)
        $tries = array(
            array('label' => 'number+message', 'body' => array('number' => $e164, 'message' => (string)$mensaje)),
            array('label' => 'to+message',     'body' => array('to' => $e164,     'message' => (string)$mensaje)),
            array('label' => 'to+text',        'body' => array('to' => $e164,     'text' => (string)$mensaje)),
            array('label' => 'jid+message',    'body' => array('number' => $jid,  'message' => (string)$mensaje)),
            array('label' => 'to(JID)+message', 'body' => array('to' => $jid,      'message' => (string)$mensaje)),
            array('label' => 'to(JID)+text',   'body' => array('to' => $jid,      'text' => (string)$mensaje)),
        );

        $results = array();
        foreach ($tries as $t) {
            $r = $this->curl_json_post($base, $t['body'], 12, $token);
            $results[] = array('variant' => $t['label'], 'http' => $r['http'], 'raw' => $r['raw'], 'json' => $r['json'], 'err' => $r['err']);

            if ($r['ok']) {
                $st = isset($r['json']['status']) ? strtoupper($r['json']['status']) : '';
                if ($st === 'SENT' || $st === 'QUEUED' || $st === 'OK' || $st === 'DELIVERED') {
                    return array('ok' => true, 'variant' => $t['label'], 'response' => $r['json']);
                }
                // 2xx sin campo status claro: lo damos por bueno y devolvemos para inspección
                return array('ok' => true, 'variant' => $t['label'], 'note' => '2xx sin status claro', 'raw' => $r['raw'], 'json' => $r['json']);
            }
        }

        // Si llegó aquí, ninguna variante 2xx → devolvemos diagnóstico completo
        return array(
            'ok'     => false,
            'msg'    => 'Mensaje no enviado. Verifica sesión CONNECTED, formato número/JID, nombres de campos y token.',
            'tests'  => $results,
            'tips'   => array(
                '1' => 'Confirma /api/status = CONNECTED (y que no requiera token)',
                '2' => 'Si tu API requiere Bearer token, pásalo en $token',
                '3' => 'Revisa los logs del proceso (pm2 logs ...) justo al hacer el POST',
            ),
        );
    }

    public function to_e164($num, $country = '593')
    {
        $digits = preg_replace('/\D+/', '', (string)$num);
        if (strpos($digits, $country) === 0) return $digits;
        if (substr($digits, 0, 1) === '0') $digits = substr($digits, 1);
        return $country . $digits;
    }


    public function curl_json_post($url, $payload, $timeout = 12, $token = null)
    {
        $ch = curl_init($url);
        $headers = array('Accept: application/json', 'Content-Type: application/json');
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ));

        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array(
            'ok'   => ($err === '' && $code >= 200 && $code < 300),
            'http' => $code,
            'raw'  => $body,
            'json' => json_decode($body, true),
            'err'  => $err,
        );
    } */



    //Envío de Imagen y PDF
    public function enviar_archivo_whatsapp($puerto, $telefono, $mensaje, $archivo)
    {
        $url = "http://137.184.159.242:" . $puerto . "/api/whatsapp-file";

        // Verificar si el archivo existe
        if (!file_exists($archivo)) {
            die("Error: El archivo no existe en la ruta especificada.");
        }

        $data = [
            "number" => $telefono,
            "caption" => $mensaje,
            "file"    => new CURLFile($archivo)
        ];

        // Inicializar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: multipart/form-data"]);

        // 🔹 Aumentar tiempos de espera
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Esperar hasta 60 segundos
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Tiempo de conexión

        // 🔹 Permitir redirecciones si la API las usa
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        // 🔹 Deshabilitar verificación SSL (si la API usa HTTPS sin certificado válido)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Ejecutar la solicitud
        $response = curl_exec($ch);

        // Capturar errores de cURL
        if (curl_errno($ch)) {
            die("Error en la petición: " . curl_error($ch));
        }

        curl_close($ch);

        // Verificar si la API respondió correctamente
        if ($response === false) {
            die("Error: No se recibió respuesta de la API.");
        }

        // Decodificar JSON
        $responseData = json_decode($response, true);

        // Verificar si la respuesta es válida
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Error al decodificar JSON: " . json_last_error_msg() . " - Respuesta recibida: " . $response);
        }

        return $responseData;
    }
}

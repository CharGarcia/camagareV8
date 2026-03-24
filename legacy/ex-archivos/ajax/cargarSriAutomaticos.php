<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Guayaquil');
include('/var/www/html/sistema/conexiones/conectalogin.php');
include('/var/www/html/sistema/clases/lee_xml.php');

if (isset($_POST['action']) && $_POST['action'] === 'cargar_otros_periodos') {
    $documento    = $_POST['tipo_documento'];
    $anio         = $_POST['anio_descarga'];
    $mes          = $_POST['mes_descarga'];
    $dia          = $_POST['dia_descarga'];
    $ruc_empresa  = $_POST['ruc_empresa_descarga'];
    $clave_sri    = $_POST['clave_sri_descargas'];
    $con = conenta_login();

    $descarga_otros_periodos = descarga_otros_periodos($documento, $anio, $mes, $dia, $ruc_empresa, $clave_sri);
    //print_r($descarga_otros_periodos);
    $documentos_almacenados = array();
    $documentos_almacenados[] = pasa_documentos_almacenados($con, $documento, $anio, $mes, $dia, $ruc_empresa);
    $respuesta_final = descarga_xml_guardado($con, $documentos_almacenados, $ruc_empresa);
    //print_r($respuesta_final);

    foreach ($respuesta_final as $message) {
        if ($message['estado'] == '0') {
?>
            <div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
                <b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
            </div>
        <?php
        }
        if ($message['estado'] == '1') {
        ?>
            <div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
                <b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
            </div>
        <?php
        }
        if ($message['estado'] == '2') {
        ?>
            <div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-warning" role="alert">
                <b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
            </div>
<?php
        }
    }
} else {
    $con = conenta_login();
    $descargas_automaticas = descargas_automaticas_diarias($con);
    echo $descargas_automaticas;
}


function descargas_automaticas_diarias($con)
{
    $fecha_actual = new DateTime();
    $dia_del_mes = $fecha_actual->format('d');
    $dia = "0";

    // Si es el primer día del mes, usamos el mes anterior
    if ($dia_del_mes == '01') {
        $fecha_anterior = clone $fecha_actual;
        $fecha_anterior->modify('-1 month');
        $anio_anterior = $fecha_anterior->format('Y');
        $mes_anterior = $fecha_anterior->format('m');
    } else {
        $anio_anterior = $fecha_actual->format('Y');
        $mes_anterior = $fecha_actual->format('m');
    }

    $tipos_documentos = [1, 2, 3, 4, 6];
    $query_empresas_sri = mysqli_query($con, "SELECT ruc FROM descargasri WHERE status = 1 AND password IS NOT NULL AND password != ''");
    while ($row_empresas_sri = mysqli_fetch_array($query_empresas_sri)) {
        $ruc_empresa_sri = $row_empresas_sri['ruc'];

        $query_empresa_activa = mysqli_query($con, "SELECT estado FROM empresas WHERE ruc = '$ruc_empresa_sri'");
        $row_estado_empresa = mysqli_fetch_array($query_empresa_activa);

        if ($row_estado_empresa && $row_estado_empresa['estado'] == 1) {
            // Empresa activa: recorrer todos los tipos de documentos
            $documentos_encontrados = [];

            foreach ($tipos_documentos as $documento) {
                $resultado = pasa_documentos_almacenados($con, $documento, $anio_anterior, $mes_anterior, $dia, $ruc_empresa_sri);
                if (!empty($resultado)) {
                    $documentos_encontrados[] = $resultado;
                }
            }

            if (!empty($documentos_encontrados)) {
                $resumen_final = descarga_xml_guardado($con, $documentos_encontrados, $ruc_empresa_sri);
                print_r($resumen_final);
            }
        } else {
            // Empresa inactiva
            mysqli_query($con, "UPDATE descargasri SET status = 0 WHERE ruc = '$ruc_empresa_sri'");
        }
    }
}


//para descargar de otros periodos
function descarga_otros_periodos($documento, $anio, $mes, $dia, $ruc_empresa, $clave_sri)
{
    $url_descarga = "http://159.89.235.139:3001/api/sri-doc-recibidos";

    $data_descarga = array(
        "ruc" => (string) $ruc_empresa,
        "password" => (string) $clave_sri,
        "anio" => (string) $anio,
        "mes" => (string) (int) $mes,
        "dia" => (string) (int) $dia,
        "tipoComprobante" => (string) $documento
    );

    $ch = curl_init($url_descarga);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode($data_descarga),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 20,
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    /*  if ($curl_error) {
        echo '❌ Error en la petición CURL: ' . $curl_error;
        return null;
    }

    if ($http_code !== 200) {
        echo '❌ Error HTTP: ' . $http_code;
        return null;
    } */

    $responseData = json_decode($response, true);

    /* if (!isset($responseData['success']) || !$responseData['success']) {
        echo '❌ Error: ' . ($responseData['listError']['errorInfo'] ?? 'Error desconocido');
        return null;
    }

    if (empty($responseData['data'])) {
        echo '⚠️ No existen datos para los parámetros ingresados.';
        return [];
    } */

    return $responseData['data'];
}





//me trae los documentos que estan guardados en el otro servidor
function pasa_documentos_almacenados($con, $documento, $anio, $mes, $dia, $ruc_empresa)
{
    $claves_a_eliminar = [];
    $query_no_descargar = mysqli_query($con, "SELECT clave_acceso FROM claves_sri_no_descargar WHERE ruc_empresa='" . $ruc_empresa . "'");
    while ($row = mysqli_fetch_assoc($query_no_descargar)) {
        $claves_a_eliminar[] = $row['clave_acceso'];
    }

    $url_almacenados = "http://159.89.235.139:3001/api/search-sri-doc-recibidos";
    $data_almacenados = [
        "ruc" => (string) $ruc_empresa,
        "anio" => (string) $anio,
        "mes" => (string) ltrim($mes, '0'),
        "dia" => (string) $dia,
        "tipoComprobante" => (string) $documento
    ];

    $ch = curl_init($url_almacenados);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_almacenados));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Error en la petición: ' . curl_error($ch);
        return [];
    }
    curl_close($ch);

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error al decodificar respuesta JSON";
        return [];
    }

    $documentos_descargados = [];

    foreach ($responseData as $registro) {
        // Asegura que existan las claves necesarias
        if (isset($registro['CLAVE_ACCESO']) && isset($registro['xmlUrl'])) {
            $clave = $registro['CLAVE_ACCESO'];
            $xml = $registro['xmlUrl'];

            // Filtra los que no deben descargarse
            if (!in_array($clave, $claves_a_eliminar)) {
                $documentos_descargados[] = [
                    'claveAcceso' => $clave,
                    'xmlUrl' => $xml
                ];
            }
        }
    }

    return varificar_claves_registradas($con, $documentos_descargados, $documento);
}


//lee el xml descargado al otro servidor
function descarga_xml_guardado($con, $clavesAccesoRegistrar, $ruc_empresa)
{
    $id_usuario = 1;
    $rides_sri = new rides_sri();
    $respuestas = [];
    // Aplana el array
    $claves_flat = [];
    foreach ($clavesAccesoRegistrar as $subgrupo) {
        if (is_array($subgrupo)) {
            foreach ($subgrupo as $item) {
                $claves_flat[] = $item;
            }
        }
    }

    foreach ($claves_flat as $valores) {
        if (!isset($valores['xmlUrl'])) continue;

        $ruta_relativa = ltrim($valores['xmlUrl'], '/');
        $url = "http://159.89.235.139:3001/" . $ruta_relativa;
        $object_xml = $rides_sri->lee_xml($url);
        $respuestas[] = $rides_sri->lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con);
    }

    return $respuestas;
}


//para ver si las claves ya estan registradas en el sistema
function varificar_claves_registradas($con, $documentos_descargados, $tipo_documento)
{
    $xml_urls = array();
    $tabla_por_tipo = [
        "1" => "encabezado_compra",
        "2" => "encabezado_liquidacion",
        "3" => "encabezado_compra",
        "4" => "encabezado_compra",
        "6" => "encabezado_retencion_venta",
    ];

    if (!isset($tabla_por_tipo[$tipo_documento])) {
        return $xml_urls; // tipo de documento no válido
    }

    $tabla = $tabla_por_tipo[$tipo_documento];

    foreach ($documentos_descargados as $documento) {
        if (!isset($documento['claveAcceso']) || !isset($documento['xmlUrl'])) {
            continue;
        }

        $claveAcceso = mysqli_real_escape_string($con, $documento['claveAcceso']);
        $query = mysqli_query($con, "SELECT 1 FROM $tabla WHERE aut_sri = '$claveAcceso' LIMIT 1");

        if (mysqli_num_rows($query) == 0) {
            $xml_urls[] = [
                'claveAcceso' => $documento['claveAcceso'],
                'xmlUrl' => $documento['xmlUrl'],
            ];
        }
    }

    return $xml_urls;
}

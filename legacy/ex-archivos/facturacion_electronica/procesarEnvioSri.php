<?php
include_once("../conexiones/conectalogin.php");
$con = conenta_login();
if (isset($_POST['id_documento_sri']) && isset($_POST['tipo_documento_sri'])) {
    // Recibir los parámetros
    $idDocumentoSri = escapeshellarg($_POST['id_documento_sri']);
    $tipoDocumentoSri = escapeshellarg($_POST['tipo_documento_sri']);
    $modo_envioSri = escapeshellarg($_POST['modo_envio']);

    //primero actualizar el estado del documento
    switch ($_POST['tipo_documento_sri']) {
        case "factura":
            $tabla = "encabezado_factura";
            $id_encabezado_documento = "id_encabezado_factura";
            break;
        case "retencion":
            $tabla = "encabezado_retencion";
            $id_encabezado_documento = "id_encabezado_retencion";
            break;
        case "nc":
            $tabla = "encabezado_nc";
            $id_encabezado_documento = "id_encabezado_nc";
            break;
        case "gr":
            $tabla = "encabezado_gr";
            $id_encabezado_documento = "id_encabezado_gr";
            break;
        case "liquidacion":
            $tabla = "encabezado_liquidacion";
            $id_encabezado_documento = "id_encabezado_liq";
            break;
        case "proforma":
            $tabla = "encabezado_proforma";
            $id_encabezado_documento = "id_encabezado_proforma";
            break;
    }
    $result_info_documento = mysqli_query($con, "UPDATE $tabla SET estado_sri='ENVIANDO' WHERE $id_encabezado_documento = '" . $_POST['id_documento_sri'] . "'");

    $rutaArchivo = '/var/www/html/sistema/facturacion_electronica/enviarComprobantesSri.php';
    // Construye el comando
    //$logFile = "/var/www/html/sistema/facturacion_electronica/log_envio_dosc_sri.log";
    $comando = "php $rutaArchivo $idDocumentoSri $tipoDocumentoSri $modo_envioSri > /dev/null 2>&1 &";
    //$comando = "php $rutaArchivo $idDocumentoSri $tipoDocumentoSri $modo_envioSri > /dev/null 2>>$logFile &";
    // Ejecuta el comando
    exec($comando, $output, $return_var);
} else {
    echo "Error: No se recibieron los parámetros necesarios.";
}

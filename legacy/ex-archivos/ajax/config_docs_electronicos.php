<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para enviar un correo y ver si funciona el envio
if ($action == 'probar_correo') {
    $correo_asunto = $_GET['correo_asunto'];
    $correo_pass = $_GET['correo_pass'];
    $correo_remitente = $_GET['correo_remitente'];
    $correo_port = $_GET['correo_port'];
    $correo_host = $_GET['correo_host'];

    $data = array(
        'template' => 'probar_correo',
        'empresa' => "CaMaGaRe",
        'asunto' => $correo_asunto,
        'pass' => $correo_pass,
        'emisor' => $correo_remitente,
        'port' => $correo_port,
        'receptor' => $correo_remitente,
        'host' => $correo_host
    );

    if (sendEmail($data)) {
        echo "<script>$.notify('Correo enviado.','success')</script>";
    } else {
        echo "<script>$.notify('Error al enviar correo.','error')</script>";
    }
}

//para guardar la informacion del correo
if ($action == 'guarda_correo_electronico') {
    if (empty($_POST['id_emisor_correo'])) {
        echo "<script>$.notify('Actualice la página, para actualizar.','error')";
    } else if (empty($_POST['correo_asunto'])) {
        echo "<script>$.notify('Ingrese el asunto del correo.','error')";
    } else if (!empty($_POST['id_emisor_correo'])) {
        $id_emisor = mysqli_real_escape_string($con, (strip_tags($_POST["id_emisor_correo"], ENT_QUOTES)));
        $correo_asunto = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["correo_asunto"]), ENT_QUOTES)));
        $correo_host = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["correo_host"]), ENT_QUOTES)));
        $correo_port = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["correo_port"]), ENT_QUOTES)));
        $correo_remitente = mysqli_real_escape_string($con, (strip_tags($_POST["correo_remitente"], ENT_QUOTES)));
        $correo_pass = mysqli_real_escape_string($con, (strip_tags($_POST["correo_pass"], ENT_QUOTES)));

        //actualiza el correo
        $query_update = mysqli_query($con, "UPDATE config_electronicos SET 
        id_usuario='" . $id_usuario . "', 
        correo_asunto='" . $correo_asunto . "',
        correo_host='" . $correo_host . "',
		correo_port='" . $correo_port . "', 
        correo_remitente = '" . $correo_remitente . "', 
        correo_pass = '" . $correo_pass . "'
                WHERE id_config= '" . $id_emisor . "'");
        if ($query_update) {
            echo "<script>
					$.notify('Los datos se actualizaron correctamente.','success');
					setTimeout(function () {location.reload()}, 60 * 20)</script>
					</script>";
        } else {
            echo "<script>$.notify('No se actualizó, intenta de nuevo.','error')</script>";
        }
    } else {
        echo "<script>$.notify('Error desconocido, intenta de nuevo.','error')</script>";
    }
}


//para guardar la informacion del emisor
if ($action == 'guarda_informacion_emisor') {
    if (empty($_POST['id_emisor'])) {
        echo "<script>$.notify('Actualice la página, para actualizar.','error')</script>";
    } else if (!empty($_POST['id_emisor'])) {
        $id_emisor = mysqli_real_escape_string($con, (strip_tags($_POST["id_emisor"], ENT_QUOTES)));
        $resol_ce = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["resol_ce"]), ENT_QUOTES)));
        $ssl = mysqli_real_escape_string($con, (strip_tags($_POST["ssl"], ENT_QUOTES)));
        $agente_retencion = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["agente_ret"]), ENT_QUOTES)));
        $tipo_regimen = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_regimen"], ENT_QUOTES)));
        $tipo_ambiente = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_ambiente"], ENT_QUOTES)));
        $tipo_emision = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_emision"], ENT_QUOTES)));

        //actualiza la clave y status para descarga de documentos del sri
        $ruc_descargas_sri = substr($ruc_empresa, 0, 10) . "001";
        $id_descargas_sri = mysqli_real_escape_string($con, (strip_tags($_POST["id_ruc_descargas"], ENT_QUOTES)));
        $clave_sri = mysqli_real_escape_string($con, (strip_tags($_POST["clave_sri"], ENT_QUOTES)));
        $status_descargas_sri = mysqli_real_escape_string($con, (strip_tags($_POST["status_descargas_sri"], ENT_QUOTES)));
        $descarga = mysqli_real_escape_string($con, (strip_tags($_POST["periodo_descargas_sri"], ENT_QUOTES)));

        if (empty($clave_sri)) {
            $status_descargas_sri = 0;
        }

        if (!empty($id_descargas_sri)) {
            $sql_delete = mysqli_query($con, "DELETE FROM descargasri WHERE ruc= '" . $ruc_descargas_sri . "' and id != '" . $id_descargas_sri . "' ");
            $actualizar = mysqli_query($con, "UPDATE descargasri SET status = '" . $status_descargas_sri . "', password = '" . $clave_sri . "' WHERE id= '" . $id_descargas_sri . "' ");
        } else {
            $sql_delete = mysqli_query($con, "DELETE FROM descargasri WHERE ruc= '" . $ruc_descargas_sri . "' ");
            $nuevo_registro = mysqli_query($con, "INSERT INTO descargasri 
	    VALUE (null, '" . $ruc_descargas_sri . "', '" . $clave_sri . "', 	'1')");
        }

        //actualiza el emisor
        $query_update = mysqli_query($con, "UPDATE config_electronicos SET 
        id_usuario='" . $id_usuario . "', 
        ssl_hab='" . $ssl . "',
        tipo_ambiente='" . $tipo_ambiente . "',
		tipo_emision='" . $tipo_emision . "', 
        resol_cont = '" . $resol_ce . "', 
        agente_ret = '" . $agente_retencion . "', 
        tipo_regimen = '" . $tipo_regimen . "',
        descarga = '" . $descarga . "' 
                WHERE id_config= '" . $id_emisor . "'");
        if ($query_update) {
            echo "<script>
					$.notify('Los datos se actualizaron correctamente.','success');
					setTimeout(function () {location.reload()}, 60 * 20)</script>
					</script>";
        } else {
            echo "<script>$.notify('No se actualizó, intenta de nuevo.','error')</script>";
        }
    } else {
        echo "<script>$.notify('Error desconocido, intenta de nuevo.','error')</script>";
    }
}

//para guardar la informacion general de la empresa
if ($action == 'guarda_informacion_general') {
    if (empty($_POST['razon_social'])) {
        echo "<script>$.notify('Ingrese razón social.','error')</script>";
    } else if (empty($_POST['direccion'])) {
        echo "<script>$.notify('Ingrese dirección de la matriz.','error')</script>";
    } else if (empty($_POST['tipo_contribuyente'])) {
        echo "<script>$.notify('Seleccione tipo de contribuyente.','error')</script>";
    } else if (empty($_POST['mail_empresa'])) {
        echo "<script>$.notify('Ingrese un correo.','error')</script>";
    } elseif (!filter_var($_POST['mail_empresa'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Su dirección de correo electrónico no está en un formato de correo electrónico válida";
        echo "<script>$.notify('Su dirección de correo electrónico no está en un formato de correo electrónico válida.','error')";
    } else if (empty($_POST['provincia'])) {
        echo "<script>$.notify('Seleccione una provincia.','error')</script>";
    } else if (empty($_POST['ciudad'])) {
        echo "<script>$.notify('Seleccione una ciudad.','error')</script>";
    } else if (
        !empty($_POST['razon_social'])
        && !empty($_POST['direccion'])
        && !empty($_POST['tipo_contribuyente'])
        && !empty($_POST['mail_empresa'])
        && !empty($_POST['provincia']) && !empty($_POST['ciudad'])
    ) {
        // escaping, additionally removing everything that could be (html/javascript-) code
        $id_empresa = mysqli_real_escape_string($con, (strip_tags($_POST["id_empresa"], ENT_QUOTES)));
        $razon_social = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["razon_social"]), ENT_QUOTES)));
        $nombre_comercial = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["nombre_comercial"]), ENT_QUOTES)));
        if (empty($nombre_comercial)) {
            $nombre_comercial = $razon_social;
        }
        $direccion = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["direccion"]), ENT_QUOTES)));
        $telefono = mysqli_real_escape_string($con, (strip_tags($_POST["telefono"], ENT_QUOTES)));
        $tipo = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_contribuyente"], ENT_QUOTES)));
        $representante_legal = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["representante_legal"]), ENT_QUOTES)));
        $id_representante_legal = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["id_representante_legal"]), ENT_QUOTES)));
        $mail = mysqli_real_escape_string($con, (strip_tags($_POST["mail_empresa"], ENT_QUOTES)));
        $provincia = mysqli_real_escape_string($con, (strip_tags($_POST["provincia"], ENT_QUOTES)));
        $ciudad = mysqli_real_escape_string($con, (strip_tags($_POST["ciudad"], ENT_QUOTES)));
        $nombre_contador = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["nombre_contador"]), ENT_QUOTES)));
        $ruc_contador = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["ruc_contador"]), ENT_QUOTES)));

        $query_update = mysqli_query($con, "UPDATE empresas SET 
                                    nombre='" . $razon_social . "', 
                                    nombre_comercial='" . $nombre_comercial . "', 
                                    direccion='" . $direccion . "', 
                                    telefono='" . $telefono . "', 
                                    tipo='" . $tipo . "', 
                                    nom_rep_legal='" . $representante_legal . "', 
                                    ced_rep_legal='" . $id_representante_legal . "', 
                                    mail='" . $mail . "',
                                    cod_prov='" . $provincia . "',
                                    cod_ciudad='" . $ciudad . "',
                                    id_usuario='" . $id_usuario . "', 
                                    nombre_contador='" . $nombre_contador . "', 
                                    ruc_contador='" . $ruc_contador . "' 
                                    WHERE id='" . $id_empresa . "'");
        if ($query_update) {
            echo "<script>$.notify('Los datos se actualizaron correctamente.','success');
            setTimeout(function () {location.reload()}, 60 * 20)</script>";
        } else {
            echo "<script>$.notify('No se actualizó, intenta de nuevo.','error')</script>";
            //$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
        }
    } else {
        echo "<script>$.notify('Error desconocido, intenta de nuevo.','error')</script>";
    }
}

if ($action == 'tipo_empresa') {
    $sql = mysqli_query($con, "SELECT codigo, nombre FROM tipo_empresa order by nombre asc");
    $tipo_empresa = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $tipo_empresa[] = $row;
    }

    // Retornar datos como JSON
    header('Content-Type: application/json');
    echo json_encode($tipo_empresa);
}

if ($action == 'ciudades') {
    $provincia = isset($_GET['provincia']) ? mysqli_real_escape_string($con, $_GET['provincia']) : '';
    $ruc_empresa = isset($ruc_empresa) ? mysqli_real_escape_string($con, $ruc_empresa) : ''; // Asegúrate de que esta variable esté definida

    if (empty($provincia) || empty($ruc_empresa)) {
        http_response_code(400); // Código 400: Solicitud incorrecta
        echo json_encode(["error" => "Provincia o RUC de empresa no especificado"]);
        exit;
    }

    $sql = mysqli_query(
        $con,
        "SELECT ciu.codigo as codigo, ciu.nombre as nombre
         FROM ciudad AS ciu
         WHERE ciu.cod_prov = '$provincia'"
    );

    $ciudad = [];
    if ($sql) {
        while ($row = mysqli_fetch_assoc($sql)) {
            $ciudad[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($ciudad);
    } else {
        http_response_code(500); // Código 500: Error interno del servidor
        echo json_encode(["error" => "Error al ejecutar la consulta"]);
    }
}



if ($action == 'provincias') {
    $sql = mysqli_query($con, "SELECT codigo, nombre FROM provincia order by nombre asc");
    $provincias = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $provincias[] = $row;
    }

    // Retornar datos como JSON
    header('Content-Type: application/json');
    echo json_encode($provincias);
}

if ($action == 'informacion_general') {
    $busca_empresa = mysqli_query($con, "SELECT emp.id as id, emp.nombre as razon_social, 
    emp.nombre_comercial as nombre_comercial, emp.ruc as ruc, suc.serie as establecimiento,
    emp.telefono as telefono, emp.direccion as direccion, emp.tipo as tipo_contribuyente, emp.mail as mail,
    emp.nom_rep_legal as representante_legal, emp.ced_rep_legal as ced_rep_legal, emp.nombre_contador as nombre_contador, 
    emp.ruc_contador as ruc_contador, emp.cod_prov as cod_prov, emp.cod_ciudad as cod_ciudad
    FROM empresas as emp 
    INNER JOIN sucursales as suc ON suc.ruc_empresa=emp.ruc 
    WHERE emp.ruc = '" . $ruc_empresa . "'");
    $row_empresa = mysqli_fetch_array($busca_empresa);
    $id_empresa = $row_empresa['id'];
    $razon_social = $row_empresa['razon_social'];
    $nombre_comercial = $row_empresa['nombre_comercial'];
    $ruc = $row_empresa['ruc'];
    $establecimiento = $row_empresa['establecimiento'];
    $telefono = $row_empresa['telefono'];
    $direccion = $row_empresa['direccion'];
    $tipo_contribuyente = $row_empresa['tipo_contribuyente'];
    $mail = $row_empresa['mail'];
    $representante_legal = $row_empresa['representante_legal'];
    $ced_rep_legal = $row_empresa['ced_rep_legal'];
    $nombre_contador = $row_empresa['nombre_contador'];
    $ruc_contador = $row_empresa['ruc_contador'];
    $cod_prov = $row_empresa['cod_prov'];
    $cod_ciudad = $row_empresa['cod_ciudad'];

    $data = array(
        'id_empresa' => $id_empresa,
        'razon_social' => $razon_social,
        'nombre_comercial' => $nombre_comercial,
        'ruc' => substr($ruc, 0, 10) . "001",
        'establecimiento' => substr($establecimiento, 0, 3),
        'telefono' => $telefono,
        'direccion' => $direccion,
        'tipo_contribuyente' => $tipo_contribuyente,
        'mail' => $mail,
        'representante_legal' => $representante_legal,
        'ced_rep_legal' => $ced_rep_legal,
        'nombre_contador' => $nombre_contador,
        'ruc_contador' => $ruc_contador,
        'provincia' => $cod_prov,
        'ciudad' => $cod_ciudad
    );

    if ($busca_empresa) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}


if ($action == 'informacion_emisor') {
    $busca_info_fe = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
    $info_fe = mysqli_fetch_array($busca_info_fe);

    $ruc_empresa_descargas = substr($ruc_empresa, 0, 10);
    $query_status_descargas = mysqli_query($con, "SELECT * FROM descargasri WHERE mid(ruc,1,10) = '" . $ruc_empresa_descargas . "' ");
    $row_status_descarga = mysqli_fetch_array($query_status_descargas);
    $id_ruc_descarga = isset($row_status_descarga['id']) ? $row_status_descarga['id'] : "";
    $status_ruc = isset($row_status_descarga['status']) ? $row_status_descarga['status'] : 0;
    $password_sri = isset($row_status_descarga['password']) ? $row_status_descarga['password'] : "";

    $data = array(
        'id_ruc_descargas' => $id_ruc_descarga,
        'id_emisor' => $info_fe['id_config'],
        'resol_cont' => $info_fe['resol_cont'],
        'ssl_hab' => $info_fe['ssl_hab'],
        'agente_ret' => $info_fe['agente_ret'],
        'tipo_regimen' => $info_fe['tipo_regimen'],
        'tipo_ambiente' => $info_fe['tipo_ambiente'],
        'tipo_emision' => $info_fe['tipo_emision'],
        'descarga' => $info_fe['descarga'],
        'clave_sri' => $password_sri,
        'status_descargas' => $status_ruc
    );

    if ($busca_info_fe) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}


if ($action == 'informacion_correo') {
    $busca_info_fe = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
    $info_fe = mysqli_fetch_array($busca_info_fe);

    $data = array(
        'id_emisor_correo' => $info_fe['id_config'],
        'correo_asunto' => $info_fe['correo_asunto'],
        'correo_host' => $info_fe['correo_host'],
        'correo_pass' => $info_fe['correo_pass'],
        'correo_port' => $info_fe['correo_port'],
        'correo_remitente' => $info_fe['correo_remitente']
    );

    if ($busca_info_fe) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}

//para mostrar la informacion de la firma electronica
if ($action == 'informacion_firma_electronica') {
    $busca_info_fe = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
    $info_fe = mysqli_fetch_array($busca_info_fe);

    $data = array(
        'id_firma' => $info_fe['id_config'],
        'archivo_firma' => '<a href="../facturacion_electronica/firma_digital/' . $info_fe['archivo_firma'] . '" title="Descargar" download><br>Descargar firma electrónica aquí</a>',
        'pass_firma' => $info_fe['pass_firma'],
        'fecha_fin_firma' => date('d-m-Y', strtotime($info_fe['fecha_fin_firma']))
    );

    if ($busca_info_fe) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}

//para mostrar informacion del estableciemiento
if ($action == 'informacion_establecimiento') {
    $serie_sucursal = $_GET['serie_sucursal'];
    $sql = mysqli_query($con, "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' and serie ='" . $serie_sucursal . "'");
    $info_secuenciales = mysqli_fetch_array($sql);

    $data = array(
        'id_establecimiento' => $info_secuenciales['id_sucursal'],
        'direccion_sucursal' => $info_secuenciales['direccion_sucursal'],
        'id_sucursal' => $info_secuenciales['id_sucursal'],
        'nombre_sucursal' => $info_secuenciales['nombre_sucursal'],
        'moneda_sucursal' => $info_secuenciales['moneda_sucursal'],
        'inicial_factura' => $info_secuenciales['inicial_factura'],
        'inicial_nc' => $info_secuenciales['inicial_nc'],
        'inicial_nd' => $info_secuenciales['inicial_nd'],
        'inicial_gr' => $info_secuenciales['inicial_gr'],
        'inicial_cr' => $info_secuenciales['inicial_cr'],
        'inicial_liq' => $info_secuenciales['inicial_liq'],
        'decimal_doc' => $info_secuenciales['decimal_doc'],
        'decimal_cant' => $info_secuenciales['decimal_cant'],
        'inicial_proforma' => $info_secuenciales['inicial_proforma'],
        'impuestos_recibo' => $info_secuenciales['impuestos_recibo'],
        'logo_establecimiento' => '<a href="../logos_empresas/' . $info_secuenciales['logo_sucursal'] . '" title="Descargar" download>
                                    <img src="../logos_empresas/' . $info_secuenciales['logo_sucursal'] . '" alt="Logo Establecimiento" style="max-width: 100px; max-height: 100px;"><br>Descargar aquí
                               </a>'
    );

    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }

    echo json_encode($arrResponse); //, JSON_UNESCAPED_UNICODE
    die();
}

if ($action == 'verificar_firma') {
    if (empty($_FILES["archivo"]["name"])) {
        echo "<script>$.notify('Seleccione el archivo de la firma.','error')";
    } else if (empty($_POST['clave_firma'])) {
        echo "<script>$.notify('Ingrese la contraseña de la firma electrónica.','error')";
    } else if (!empty($_POST['clave_firma'])) {
        $clave_firma = $_POST["clave_firma"];
        if (!empty($_FILES["archivo"]["name"])) {
            $b = explode(".", $_FILES['archivo']['name']); //divide la cadena por el punto y lo guarda en un arreglo
            $e = count($b); //calcula el número de elementos del arreglo b
            $ext_file = $b[$e - 1]; //captura la extensión del archivo.
            $nombre_archivo_firma = codigo_aleatorio(10) . "." . $ext_file; //crea el path de destino del archivo
            $archivo_firma = "../facturacion_electronica/firma_digital/" . $nombre_archivo_firma;

            $target_dir = "../facturacion_electronica/firma_digital/";
            $archivo_name = time() . "_" . basename($_FILES["archivo"]["name"]);
            $target_file = $target_dir . $archivo_name;
            $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
            $imageFileZise = $_FILES["archivo"]["size"];

            if (($imageFileType != "p12") && $imageFileZise > 0) {
                echo "<script>$.notify('Lo sentimos, sólo se permiten archivos .p12','error')</script>";
            } else if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_firma)) {
                echo "<script>$.notify('Error al cargar, revise el tipo de archivo','error')</script>";
            } else {
                $fname = $archivo_firma;
                $f = fopen($fname, "r");
                $cert = fread($f, filesize($fname));
                fclose($f);
                $datos = array();
                if (!openssl_pkcs12_read($cert, $datos, $clave_firma)) {
                    echo "<script>$.notify('Error en contraseña de firma electrónica','error')</script>";
                } else {
                    $datos = openssl_x509_parse($datos['cert'], 0);
                    $ruc_pnj_uanataca = isset($datos['extensions']['1.3.6.1.4.1.47286.102.3.11']) ? $datos['extensions']['1.3.6.1.4.1.47286.102.3.11'] : "";
                    $ruc_pnj_security = isset($datos['extensions']['1.3.6.1.4.1.37746.3.11']) ? $datos['extensions']['1.3.6.1.4.1.37746.3.11'] : "";
                    $cedula_pn_uanataca = isset($datos['extensions']['1.3.6.1.4.1.47286.102.3.1']) ? $datos['extensions']['1.3.6.1.4.1.47286.102.3.1'] : "";
                    $cedula_pn_security = isset($datos['extensions']['1.3.6.1.4.1.37746.3.1']) ? $datos['extensions']['1.3.6.1.4.1.37746.3.1'] : "";
                    $datos_firma = array(
                        'fecha_desde' => date('d-m-Y', $datos['validFrom_time_t']),
                        'fecha_hasta' => date('d-m-Y', $datos['validTo_time_t']),
                        'emitido_para' => $datos['subject']['commonName'],
                        'emitido_por' => $datos['issuer']['organizationName'],
                        'emitido_ruc' => $ruc_pnj_uanataca . $ruc_pnj_security,
                        'cedula_pn' => $cedula_pn_uanataca . $cedula_pn_security
                    );

?>
                    <input type="hidden" id="fecha_vencimiento" value="<?php echo date('d-m-Y', $datos['validTo_time_t']); ?>">
                    <div class="alert alert-success" role="alert">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php
                        echo "<b> Proveedor de firma: " . $datos_firma['emitido_por'] . "</b></br></br>";
                        echo "<b>Fecha Emisión:</b> " . $datos_firma['fecha_desde'] . "</br>";
                        echo "<b>Fecha vencimiento:</b> " . $datos_firma['fecha_hasta'] . "</br>";
                        echo "<b>Nombre:</b> " . $datos_firma['emitido_para'] . "</br>";
                        echo "<b>Cedula:</b> " . $datos_firma['cedula_pn'] . "</br>";
                        echo "<b>Emitido para RUC:</b> " . $datos_firma['emitido_ruc'];
                        ?>
                    </div>
<?php
                    unlink($fname);
                }
            }
        } else {
            echo "<script>$.notify('Seleccione un archivo de firma electrónica .p12','error')</script>";
        }
    } else {
        echo "<script>$.notify('Error desconocido, intente de nuevo','error')</script>";
    }
}


//para guardar o actualizar la firma electronica
if ($action == 'guarda_actualiza_firma') {
    if (empty($_POST['id_firma'])) {
        echo "<script>$.notify('Vuelva a cargar la página','error')</script>";
    } else if (empty($_POST['clave_firma'])) {
        echo "<script>$.notify('Ingrese la contraseña de la firma electrónica','error')</script>";
    } else if (empty($_POST['vence_firma'])) {
        echo "<script>$.notify('Ingrese la fecha de vencimiento de la firma electrónica','error')</script>";
    } else if (!date($_POST['vence_firma'])) {
        echo "<script>$.notify('Ingrese fecha de vencimiento correcta dd/mm/aaaa','error')</script>";
    } else if (!empty($_POST['clave_firma']) && !empty($_POST['vence_firma'])) {

        $vence_firma = date('Y-m-d H:i:s', strtotime($_POST['vence_firma']));
        $clave_firma = $_POST["clave_firma"];

        $busca_empresa = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
        $count = mysqli_num_rows($busca_empresa);
        $nombre_archivo = mysqli_fetch_array($busca_empresa);

        if ($count == 0) {
            echo "<script>$.notify('Primero debe registrar la información de emisor electrónico','error')</script>";
        } else {
            if (!empty($_FILES["archivo"]["name"])) {
                $b = explode(".", $_FILES['archivo']['name']); //divide la cadena por el punto y lo guarda en un arreglo
                $e = count($b); //calcula el número de elementos del arreglo b
                $ext_file = $b[$e - 1]; //captura la extensión del archivo.
                $nombre_archivo_firma = codigo_aleatorio(10) . "." . $ext_file; //crea el path de destino del archivo
                $archivo_firma = "../facturacion_electronica/firma_digital/" . $nombre_archivo_firma;

                $target_dir = "../facturacion_electronica/firma_digital/";
                $archivo_name = time() . "_" . basename($_FILES["archivo"]["name"]);
                $target_file = $target_dir . $archivo_name;
                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                $imageFileZise = $_FILES["archivo"]["size"];

                if (($imageFileType != "p12") && $imageFileZise > 0) {
                    echo "<script>$.notify('Lo sentimos, sólo se permiten archivos .p12','error')</script>";
                } else if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo_firma)) {
                    echo "<script>$.notify('Error al cargar, revise el tipo de archivo','error')</script>";
                } else {

                    $ftp_server = "64.225.69.65";
                    $ftp_user_name = "char";
                    $ftp_user_pass = "CmGr1980";

                    $conn_id = ftp_connect($ftp_server);
                    if (@ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
                        ftp_pasv($conn_id, true);
                        $local_file = $archivo_firma;
                        $server_file = "/ftp_documentos/firma_digital/" . $nombre_archivo_firma;

                        if (ftp_put($conn_id, $server_file, $local_file, FTP_BINARY)) {
                            ftp_chmod($conn_id, 0644, $server_file);

                            $query_update = mysqli_query($con, "UPDATE config_electronicos 
                            SET id_usuario='" . $id_usuario . "', 
                            archivo_firma='" . $nombre_archivo_firma . "', 
                            pass_firma='" . $clave_firma . "', 
                            fecha_fin_firma ='" . $vence_firma . "' 
                            WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
                            if ($query_update) {
                                echo "<script>
                            $.notify('La firma se actualizó correctamente.','success');
                            setTimeout(function (){location.href ='../modulos/config_docs_electronicos.php'}, 1000);
                            </script>";
                            } else {
                                echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente','error')</script>";
                            }
                        } else {
                            echo "<script>
						$.notify('La firma no se actualizó, vuelva a intentarlo.','error');
						</script>";
                        }
                    } else {
                        echo "<script>
						$.notify('No hay conexion con el servidor ftp.','error');
						</script>";
                    }

                    ftp_close($conn_id);
                    $archivo_eliminado = "../facturacion_electronica/firma_digital/" . $nombre_archivo['archivo_firma'];
                    unlink($archivo_eliminado);
                }
            } else {
                echo "<script>$.notify('Seleccione un archivo de firma electrónica .p12','error')</script>";
            }
        }
    } else {
        echo "<script>$.notify('Intente de nuevo','error')</script>";
    }
} //fin del action de actualizar firma



//para guardar los datos del establecimiento
if ($action == 'guarda_actualiza_establecimiento') {
    if (empty($_POST['id_establecimiento'])) {
        echo "<script>$.notify('Vuelva a cargar la página','error')</script>";
    } else if (empty($_POST['serie_sucursal'])) {
        echo "<script>$.notify('Seleccione un establecimiento de la empresa','error')</script>";
    } else if (empty($_POST['moneda_sucursal'])) {
        echo "<script>$.notify('Seleccione un tipo de moneda','error')</script>";
    } else if (empty($_POST['decimales_cantidad'])) {
        echo "<script>$.notify('Seleccione cuantos decimales quiere aplicar en la cantidad','error')</script>";
    } else if (empty($_POST['decimales_documento'])) {
        echo "<script>$.notify('Seleccione cuantos decimales quiere aplicar en el precio','error')</script>";
    } else if (empty($_POST['dir_sucursal'])) {
        echo "<script>$.notify('Ingrese la dirección del establecimiento seleccionado','error')</script>";
    } else if (empty($_POST['nombre_sucursal'])) {
        echo "<script>$.notify('Ingrese nombre del establecimiento seleccionado','error')</script>";
    } else if (empty($_POST['inicial_factura'])) {
        echo "<script>$.notify('Ingrese el número inicial para las facturas electrónicas','error')</script>";
    } else if (empty($_POST['inicial_nc'])) {
        echo "<script>$.notify('Ingrese el número inicial para las notas de crédito electrónicas','error')</script>";
    } else if (empty($_POST['inicial_nd'])) {
        echo "<script>$.notify('Ingrese el número inicial para las notas de débito electrónicas','error')</script>";
    } else if (empty($_POST['inicial_gr'])) {
        echo "<script>$.notify('Ingrese el número inicial para las guías de remisión electrónicas','error')</script>";
    } else if (empty($_POST['inicial_cr'])) {
        echo "<script>$.notify('Ingrese el número inicial para los comprobantes de retención electrónicas','error')</script>";
    } else if (empty($_POST['inicial_liq'])) {
        echo "<script>$.notify('Ingrese el número inicial para las liquidaciones de compras electrónicas','error')</script>";
    } else if (empty($_POST['inicial_proforma'])) {
        echo "<script>$.notify('Ingrese el número inicial para las proformas','error')</script>";
    } else if (
        !empty($_POST['serie_sucursal'])
        && !empty($_POST['moneda_sucursal']) && !empty($_POST['dir_sucursal']) && !empty($_POST['nombre_sucursal']) && !empty($_POST['decimales_documento'])
        && !empty($_POST['inicial_factura']) && !empty($_POST['decimales_cantidad']) && !empty($_POST['inicial_liq']) && !empty($_POST['inicial_proforma'])
    ) {

        $id_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["id_establecimiento"], ENT_QUOTES)));
        $serie_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["serie_sucursal"], ENT_QUOTES)));
        $moneda_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["moneda_sucursal"], ENT_QUOTES)));
        $nombre_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["nombre_sucursal"], ENT_QUOTES)));
        $dir_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["dir_sucursal"], ENT_QUOTES)));
        $inicial_factura = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_factura"], ENT_QUOTES)));
        $inicial_nc = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_nc"], ENT_QUOTES)));
        $inicial_nd = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_nd"], ENT_QUOTES)));
        $inicial_gr = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_gr"], ENT_QUOTES)));
        $inicial_cr = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_cr"], ENT_QUOTES)));
        $inicial_liq = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_liq"], ENT_QUOTES)));
        $inicial_proforma = mysqli_real_escape_string($con, (strip_tags($_POST["inicial_proforma"], ENT_QUOTES)));
        $decimales = mysqli_real_escape_string($con, (strip_tags($_POST["decimales_documento"], ENT_QUOTES)));
        $decimales_cantidad = mysqli_real_escape_string($con, (strip_tags($_POST["decimales_cantidad"], ENT_QUOTES)));
        $impuestos_recibo = mysqli_real_escape_string($con, (strip_tags($_POST["impuestos_recibo"], ENT_QUOTES)));

        //consultar si hay un registro de esta empresa para modificar o guardar nuevo
        $busca_sucursal = "SELECT * FROM sucursales WHERE id_sucursal = '" . $id_sucursal . "' ";
        $result = $con->query($busca_sucursal);


        if (!empty($_FILES["logo_sucursal"]["name"])) {
            $b = explode(".", $_FILES['logo_sucursal']['name']); //divide la cadena por el punto y lo guarda en un arreglo
            $e = count($b); //calcula el número de elementos del arreglo b
            $ext_file = $b[$e - 1]; //captura la extensión del archivo.
            $nombre_logo_sucursal = codigo_aleatorio(10) . "."  . $ext_file; //crea el path de destino del archivo
            $logo_sucursal = "../logos_empresas/" . $nombre_logo_sucursal;
            $target_dir = "../logos_empresas/";
            $archivo_name = time() . "_" . basename($_FILES["logo_sucursal"]["name"]);
            $target_file = $target_dir . $archivo_name;
            $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
            $imageFileZise = $_FILES["logo_sucursal"]["size"];

            if (($imageFileType != "jpg" && $imageFileType != "jpeg") and $imageFileZise > 0) {
                echo "<script>$.notify('Lo sentimos, sólo se permiten archivos JPG , JPEG','error')</script>";
            } else if ($imageFileZise > 1001048576) { //1048576 byte=1MB
                echo "<script>$.notify('Lo sentimos, pero el logo es demasiado grande. Selecciona un logo de menos de 1MB','error')</script>";
            } else if (!move_uploaded_file($_FILES['logo_sucursal']['tmp_name'], $logo_sucursal)) {
                echo "<script>$.notify('Error al cargar el logo, revise el tipo de archivo','error')</script>";
            } else {
                $nombre_archivo = mysqli_fetch_array($result);
                $nombre_logo = $nombre_archivo['logo_sucursal'];
                $logo_eliminado = "../logos_empresas/" . $nombre_logo;

                if ($nombre_logo != null) {
                    unlink($logo_eliminado);
                }

                $count = mysqli_num_rows($result);
                if ($count > 0) {

                    $ftp_server = "64.225.69.65";
                    $ftp_user_name = "char";
                    $ftp_user_pass = "CmGr1980";

                    $conn_id = ftp_connect($ftp_server);
                    if (@ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
                        ftp_pasv($conn_id, true);
                        $local_file = $logo_sucursal;
                        $server_file = "/ftp_documentos/logos_empresa/" . $nombre_logo_sucursal;

                        if (ftp_put($conn_id, $server_file, $local_file, FTP_BINARY)) {
                            ftp_chmod($conn_id, 0644, $server_file);
                            $query_update = mysqli_query($con, "UPDATE sucursales SET direccion_sucursal='" . $dir_sucursal . "',
                             moneda_sucursal='" . $moneda_sucursal . "',logo_sucursal='" . $nombre_logo_sucursal . "', 
                             inicial_factura='" . $inicial_factura . "', inicial_nc='" . $inicial_nc . "',	
                             inicial_nd='" . $inicial_nd . "', inicial_gr='" . $inicial_gr . "', 
                             inicial_cr='" . $inicial_cr . "', decimal_doc ='" . $decimales . "',
                             nombre_sucursal='" . $nombre_sucursal . "', decimal_cant= '" . $decimales_cantidad . "', 
                             inicial_liq= '" . $inicial_liq . "', inicial_proforma= '" . $inicial_proforma . "', 
                             impuestos_recibo='" . $impuestos_recibo . "' 
                             WHERE ruc_empresa='" . $ruc_empresa . "' and serie = '" . $serie_sucursal . "' ");
                            if ($query_update) {
                                echo "<script>
                            $.notify('Los datos se actualizaron correctamente.','success');
                            setTimeout(function (){location.href ='../modulos/config_docs_electronicos.php'}, 1000);
                            </script>";
                            } else {
                                echo "<script>
                        $.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
                        </script>";
                            }
                        } else {
                            echo "<script>
                        $.notify('El logo no se actualizó, vuelva a intentarlo.','error');
                        </script>";
                        }
                    } else {
                        echo "<script>
                        $.notify('No hay conexion con el servidor ftp.','error');
                        </script>";
                    }

                    ftp_close($conn_id);
                }
            }
        } else {
            $count = mysqli_num_rows($result);
            if ($count > 0) {
                $sql = "UPDATE sucursales SET direccion_sucursal='" . $dir_sucursal . "', moneda_sucursal='" . $moneda_sucursal . "', inicial_factura='" . $inicial_factura . "', inicial_nc='" . $inicial_nc . "',
                        inicial_nd='" . $inicial_nd . "', inicial_gr='" . $inicial_gr . "', inicial_cr='" . $inicial_cr . "', 
                        decimal_doc ='" . $decimales . "',nombre_sucursal='" . $nombre_sucursal . "', 
                        decimal_cant= '" . $decimales_cantidad . "', inicial_liq= '" . $inicial_liq . "', 
                        inicial_proforma= '" . $inicial_proforma . "', impuestos_recibo='" . $impuestos_recibo . "'
                         WHERE id_sucursal='" . $id_sucursal . "' ";
                $query_update = mysqli_query($con, $sql);
                if ($query_update) {
                    echo "<script>
                            $.notify('Los datos se actualizaron correctamente.','success');
                            setTimeout(function (){location.href ='../modulos/config_docs_electronicos.php'}, 1000);
                            </script>";
                } else {
                    echo "<script>
                        $.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
                        </script>";
                }
            }
        }
    } else {
        echo "<script>
                        $.notify('Error desconocido.','error');
                        </script>";
    }
}

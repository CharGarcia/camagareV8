<?php
include("../validadores/ruc.php");
include("../conexiones/conecta_ftp.php");
$con_ftp = conecta_ftp();
if (empty($_POST['razon_social'])) {
	$errors[] = "Ingrese razon social";
} else if (empty($_POST['nombre_comercial'])) {
	$errors[] = "Ingrese nombre comercial";
} else if (empty($_POST['ruc'])) {
	$errors[] = "Ingrese ruc";
} else if (empty($_POST['direccion'])) {
	$errors[] = "Ingrese direccion";
} else if (empty($_POST['tipo'])) {
	$errors[] = "Seleccione tipo de empresa";
} else if (strlen($_POST['mail']) > 64) {
	$errors[] = "El correo electrónico no puede ser superior a 64 caracteres";
} else if (!filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL)) {
	$errors[] = "La dirección de correo electrónico no está en un formato de correo electrónico válida";
} else if (empty($_POST['provincia'])) {
	$errors[] = "Seleccione una provincia";
} else if (empty($_POST['ciudad'])) {
	$errors[] = "Seleccione una ciudad";
} else if (
	!empty($_POST['razon_social'])
	&& !empty($_POST['nombre_comercial'])
	&& !empty($_POST['ruc'])
	&& !empty($_POST['direccion'])
	&& ($_POST['tipo']) != '0'
	&& ($_POST['provincia']) != '0'
	&& ($_POST['ciudad']) != '0'
	&& !empty($_POST['rep_legal'])
	&& !empty($_POST['ced_rep_legal'])
	&& !empty($_POST['mail'])
	&& strlen($_POST['mail']) <= 64
	&& filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL)
) {
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	$nombre = strtoupper($_POST['razon_social']);
	$nombre_comercial = strtoupper($_POST['nombre_comercial']);
	$serie = $_POST['serie'];
	$ruc = substr($_POST['ruc'], 0, 10) . substr($_POST['serie'], 0, 3);
	$direccion = strtoupper($_POST['direccion']);
	$telefono = "";
	$tipo = $_POST['tipo'];
	$rep_legal = empty($_POST['rep_legal']) ? "" : strtoupper($_POST['rep_legal']);
	$ced_rep_legal = empty($_POST['ced_rep_legal']) ? "" : $_POST['ced_rep_legal'];
	$mail = $_POST['mail'];
	$provincia = $_POST['provincia'];
	$ciudad = $_POST['ciudad'];
	$nombre_contador = empty($_POST['nombre_contador']) ? "" : $_POST['nombre_contador'];
	$ruc_contador = empty($_POST['ruc_contador']) ? "" : $_POST['ruc_contador'];
	$tipo_regimen = $_POST['tipo_regimen'];
	$fecha_agregado = date("Y-m-d H:i:s");
	session_start();
	$id_usuario = $_SESSION['id_usuario'];

	$busca_empresa = "SELECT * FROM empresas WHERE ruc = '" . $ruc . "' ";
	$result = $con->query($busca_empresa);
	$count = mysqli_num_rows($result);
	if ($count == 1) {
		$errors[] = "La empresa que desea guardar ya está registrada." . mysqli_error($con);
	} else {
		$validaruc = validaRuc($ruc);
		if ($validaruc == "correcto") {
			// para guardar los datos de la empresa
			$query_new_empresa = mysqli_query($con, "INSERT INTO empresas VALUES(NULL,'" . $nombre . "','" . $nombre_comercial . "','" . $ruc . "','" . $direccion . "','" . $telefono . "','" . $tipo . "','" . $rep_legal . "','" . $ced_rep_legal . "','" . $mail . "','" . $provincia . "','" . $ciudad . "','1','" . $fecha_agregado . "','" . $id_usuario . "','" . $nombre_contador . "','" . $ruc_contador . "')");
			$query_new_sucursal = mysqli_query($con, "INSERT INTO sucursales VALUES(NULL,'" . $ruc . "','" . $serie . "','" . $direccion . "','" . $telefono . "','DOLAR','','1','1','1','1','1','2','" . $nombre_comercial . "','2','1','1','1')");

			//para crear las carpetas de documentos electronicos en el servidor internet	
			$carpeta_facturas = '/ftp_documentos/facturas_autorizadas/' . $ruc;
			$carpeta_guias = '/ftp_documentos/guias_autorizadas/' . $ruc;
			$carpeta_retenciones = '/ftp_documentos/retenciones_autorizadas/' . $ruc;
			$carpeta_nc = '/ftp_documentos/nc_autorizadas/' . $ruc;
			$carpeta_nd = '/ftp_documentos/nd_autorizadas/' . $ruc;
			$carpeta_liquidaciones = '/ftp_documentos/liquidaciones_autorizadas/' . $ruc;
			$carpeta_proformas = '/ftp_documentos/proformas_autorizadas/' . $ruc;

			ftp_mkdir($con_ftp, $carpeta_facturas);
			ftp_chmod($con_ftp, 0777, $carpeta_facturas);
			ftp_mkdir($con_ftp, $carpeta_guias);
			ftp_chmod($con_ftp, 0777, $carpeta_guias);
			ftp_mkdir($con_ftp, $carpeta_retenciones);
			ftp_chmod($con_ftp, 0777, $carpeta_retenciones);
			ftp_mkdir($con_ftp, $carpeta_nc);
			ftp_chmod($con_ftp, 0777, $carpeta_nc);
			ftp_mkdir($con_ftp, $carpeta_nd);
			ftp_chmod($con_ftp, 0777, $carpeta_nd);
			ftp_mkdir($con_ftp, $carpeta_liquidaciones);
			ftp_chmod($con_ftp, 0777, $carpeta_liquidaciones);
			ftp_mkdir($con_ftp, $carpeta_proformas);
			ftp_chmod($con_ftp, 0777, $carpeta_proformas);
			ftp_close($con_ftp);

			//PARA ASIGNAR UNA EMPRESA	
			//primero consulto el nuevo registro guardado
			$busca_empresa_nueva = "SELECT * FROM empresas WHERE ruc = '" . $ruc . "' ";
			$result_nueva = $con->query($busca_empresa_nueva);
			$result_nueva_empresa = mysqli_fetch_array($result_nueva);
			$id_empresa = $result_nueva_empresa['id'];
			$ruc_empresa = $result_nueva_empresa['ruc'];

			$query_new_asignada = mysqli_query($con, "INSERT INTO empresa_asignada VALUES(NULL,'" . $id_empresa . "','" . $id_usuario . "','" . $id_usuario . "','" . $fecha_agregado . "')");
			$modulo_configuracion = mysqli_query($con, "INSERT INTO modulos_asignados VALUES(NULL,'" . $id_usuario . "','" . $id_empresa . "','139','15','1','1','1','1')");
			$query_new_bodega = mysqli_query($con, "INSERT INTO bodega VALUES(NULL,'" . $ruc_empresa . "','Central','1')");
			$query_new_config = mysqli_query($con, "INSERT INTO config_electronicos VALUES(NULL,'" . $ruc_empresa . "','1','','','" . $nombre . "','','','','','false','2','1','', now(),'','NO','NO','NO','" . $tipo_regimen . "','1')");
			if ($query_new_empresa && $query_new_sucursal && $query_new_asignada && $modulo_configuracion) {
				$messages[] = "La Empresa ha sido guardada satisfactoriamente.";
?>
				<script>
					setTimeout(function() {
						location.reload()
					}, 60 * 20);
				</script>
	<?php
			} else {
				$errors[] = "Lo siento algo ha salido mal, intente nuevamente." . mysqli_error($con);
			}
		} else {
			$errors[] = $validaruc . mysqli_error($con);
		}
	}
} else {
	$errors[] = "Error desconocido.";
}

if (isset($errors)) {

	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong></strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}

?>
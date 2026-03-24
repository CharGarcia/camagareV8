<?php
include("../conexiones/conectalogin.php");
include("../validadores/ruc.php");
include("../validadores/cedula.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$fecha_agregado = date("Y-m-d H:i:s");

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'actualiza_cliente') {
	$codigo_unico = $_POST['codigo_unico'];
	$id_cliente = $_POST['id_cliente'];
	$sql = mysqli_query($con, "UPDATE encabezado_mecanica SET id_cliente='" . $id_cliente . "' WHERE codigo_unico='" . $codigo_unico . "'");
	if ($sql) {
		echo "<script>
				$.notify('Cliente actualizado','success');
				</script>";
	} else {
		echo "<script>
				$.notify('Cliente No actualizado','error');
				</script>";
	}
}


if ($action == 'agrega_cliente') {
	$id_registro = mysqli_real_escape_string($con, (strip_tags($_POST["id_registro"], ENT_QUOTES)));

	//Editar el registro de cliente y asignar el id_cliente a la orden
	if (!empty($_POST['id_cliente'])) {
		if (empty($_POST['tipo_id_cliente'])) {
			$errors[] = "Seleccione un tipo de id: cedula, ruc...";
		} else if (empty($_POST['ruc_cliente'])) {
			$errors[] = "Ingrese número de cedula ruc o pasaporte";
		} else if (empty($_POST['nombre_cliente'])) {
			$errors[] = "Ingrese nombre del cliente a quien se va a emitir la factura";
		} else if (empty($_POST['direccion_cliente'])) {
			$errors[] = "Ingrese dirección del cliente";
		} else if (empty($_POST['email_cliente'])) {
			$errors[] = "Ingrese dirección de correo electrónico";
		} elseif (!filter_var($_POST['email_cliente'], FILTER_VALIDATE_EMAIL)) {
			$errors[] = "La dirección de correo electrónico no está en un formato de correo electrónico válida";
		} else if (
			!empty($_POST['id_registro']) && filter_var($_POST['email_cliente'], FILTER_VALIDATE_EMAIL)
			&& !empty($_POST['tipo_id_cliente'])
			&& !empty($_POST['ruc_cliente'])
			&& !empty($_POST['nombre_cliente'])
			&& !empty($_POST['direccion_cliente'])
		) {
			$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
			$tipo_id = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_id_cliente"], ENT_QUOTES)));
			$ruc = mysqli_real_escape_string($con, (strip_tags($_POST["ruc_cliente"], ENT_QUOTES)));
			$nombre = mysqli_real_escape_string($con, (strip_tags($_POST["nombre_cliente"], ENT_QUOTES)));
			$direccion = mysqli_real_escape_string($con, (strip_tags($_POST["direccion_cliente"], ENT_QUOTES)));
			$telefono = mysqli_real_escape_string($con, (strip_tags($_POST["telefono_cliente"], ENT_QUOTES)));
			$email = mysqli_real_escape_string($con, (strip_tags($_POST["email_cliente"], ENT_QUOTES)));
			$plazo = mysqli_real_escape_string($con, (strip_tags($_POST["plazo_cliente"], ENT_QUOTES)));

			echo editar_orden($id_usuario, $fecha_agregado, $id_cliente, $id_registro, $con);
			//editar el cliente

			switch ($tipo_id) {
				case 05:
					$validacedula = validacedula($ruc);
					if ($validacedula == "cedula correcta") {
						echo editar_cliente($nombre, $telefono, $email, $direccion, $plazo, $id_usuario, $con, $id_cliente);
					} else {
						$errors[] = $validacedula . mysqli_error($con);
					}
					break;
				case 04:
					$validaruc = validaRuc($ruc);
					if ($validaruc == "correcto") {
						echo editar_cliente($nombre, $telefono, $email, $direccion, $plazo, $id_usuario, $con, $id_cliente);
					} else {
						$errors[] = $validaruc . mysqli_error($con);
					}
					break;
				default:
					echo editar_cliente($nombre, $telefono, $email, $direccion, $plazo, $id_usuario, $con, $id_cliente);
			}
		}
	} else {

		if (empty($_POST['tipo_id_cliente'])) {
			$errors[] = "Seleccione un tipo de id: cedula, ruc...";
		} else if (empty($_POST['ruc_cliente'])) {
			$errors[] = "Ingrese número de cedula ruc o pasaporte";
		} else if (empty($_POST['nombre_cliente'])) {
			$errors[] = "Ingrese nombre del cliente a quien se va a emitir la factura";
		} else if (empty($_POST['direccion_cliente'])) {
			$errors[] = "Ingrese dirección del cliente";
		} else if (empty($_POST['email_cliente'])) {
			$errors[] = "Ingrese dirección de correo electrónico";
		} elseif (!filter_var($_POST['email_cliente'], FILTER_VALIDATE_EMAIL)) {
			$errors[] = "La dirección de correo electrónico no está en un formato de correo electrónico válida";
		} else if (
			!empty($_POST['id_registro']) && filter_var($_POST['email_cliente'], FILTER_VALIDATE_EMAIL)
			&& !empty($_POST['tipo_id_cliente'])
			&& !empty($_POST['ruc_cliente'])
			&& !empty($_POST['nombre_cliente'])
			&& !empty($_POST['direccion_cliente'])
		) {
			$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente"], ENT_QUOTES)));
			$tipo_id = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_id_cliente"], ENT_QUOTES)));
			$ruc = mysqli_real_escape_string($con, (strip_tags($_POST["ruc_cliente"], ENT_QUOTES)));
			$nombre = mysqli_real_escape_string($con, (strip_tags($_POST["nombre_cliente"], ENT_QUOTES)));
			$direccion = mysqli_real_escape_string($con, (strip_tags($_POST["direccion_cliente"], ENT_QUOTES)));
			$telefono = mysqli_real_escape_string($con, (strip_tags($_POST["telefono_cliente"], ENT_QUOTES)));
			$email = mysqli_real_escape_string($con, (strip_tags($_POST["email_cliente"], ENT_QUOTES)));
			$plazo = mysqli_real_escape_string($con, (strip_tags($_POST["plazo_cliente"], ENT_QUOTES)));

			//si no hay id cliente hay que guardar el nuevo cliente y luego asignarle al alumno
			$busca_cliente = "SELECT * FROM clientes WHERE ruc = '" . $ruc . "' and ruc_empresa = '" . $ruc_empresa . "'";
			$result = $con->query($busca_cliente);
			$count = mysqli_num_rows($result);
			if ($count == 1) {
				$errors[] = "El cliente ya está registrado." . mysqli_error($con);
			} else {
				switch ($tipo_id) {
					case 05:
						$validacedula = validacedula($ruc);
						if ($validacedula == "cedula correcta") {
							echo guardar_cliente($ruc_empresa, $nombre, $tipo_id, $ruc, $telefono, $email, $direccion, $fecha_agregado, $plazo, $id_usuario, $con);
							echo asignar_cliente($ruc, $ruc_empresa, $nombre, $email, $direccion, $con, $id_registro);
						} else {
							$errors[] = $validacedula . mysqli_error($con);
						}
						break;
					case 04:
						$validaruc = validaRuc($ruc);
						if ($validaruc == "correcto") {
							echo guardar_cliente($ruc_empresa, $nombre, $tipo_id, $ruc, $telefono, $email, $direccion, $fecha_agregado, $plazo, $id_usuario, $con);
							echo asignar_cliente($ruc, $ruc_empresa, $nombre, $email, $direccion, $con, $id_registro);
						} else {
							$errors[] = $validaruc . mysqli_error($con);
						}
						break;
					default:
						echo guardar_cliente($ruc_empresa, $nombre, $tipo_id, $ruc, $telefono, $email, $direccion, $fecha_agregado, $plazo, $id_usuario, $con);
						echo asignar_cliente($ruc, $ruc_empresa, $nombre, $email, $direccion, $con, $id_registro);
				}
			}
		}
	}
}

if (isset($errors)) {

?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
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

function editar_orden($id_usuario, $fecha_agregado, $id_cliente, $id_registro, $con)
{
	$sql = "UPDATE encabezado_mecanica SET id_usuario = '" . $id_usuario . "',fecha_registro='" . $fecha_agregado . "',id_cliente='" . $id_cliente . "' WHERE id_enc_mecanica='" . $id_registro . "'";
	$query_update = mysqli_query($con, $sql);
	if ($query_update) {
		echo "<script>
		$.notify('Los datos para la facturación han sido actualizados.','success');
		</script>";
	} else {
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
	}
}

function editar_cliente($nombre, $telefono, $email, $direccion, $plazo, $id_usuario, $con, $id_cliente)
{
	$sql = "UPDATE clientes SET nombre='" . $nombre . "', telefono='" . $telefono . "', email='" . $email . "', direccion='" . $direccion . "', plazo= '" . $plazo . "', id_usuario = '" . $id_usuario . "'  WHERE id='" . $id_cliente . "'";
	$query_update = mysqli_query($con, $sql);
	if ($query_update) {
		echo "<script>
		$.notify('Cliente ha sido actualizado satisfactoriamente.','success');
		</script>";
		echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
	} else {
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
	}
}

function guardar_cliente($ruc_empresa, $nombre, $tipo_id, $ruc, $telefono, $email, $direccion, $fecha_agregado, $plazo, $id_usuario, $con)
{
	$sql = "INSERT INTO clientes VALUES (null,'" . $ruc_empresa . "' ,'" . $nombre . "','" . $tipo_id . "','" . $ruc . "','" . $telefono . "','" . $email . "','" . $direccion . "','" . $fecha_agregado . "','" . $plazo . "','" . $id_usuario . "','17','189',1,'')";
	$query_new_insert = mysqli_query($con, $sql);
	if ($query_new_insert) {
		echo "<script>
		$.notify('Cliente ha sido ingresado satisfactoriamente.','success');
		</script>";
	} else {
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
	}
}

function asignar_cliente($ruc, $ruc_empresa, $nombre, $email, $direccion, $con, $id_registro)
{
	$busca_cliente = "SELECT * FROM clientes WHERE ruc = '" . $ruc . "' and ruc_empresa = '" . $ruc_empresa . "' and nombre= '" . $nombre . "'  and email= '" . $email . "' and direccion = '" . $direccion . "' ";
	$result = $con->query($busca_cliente);
	$row_cliente = mysqli_fetch_array($result);
	$id_cliente = $row_cliente['id'];
	//actualizar el id_cliente 

	$sql = "UPDATE encabezado_mecanica SET id_cliente='" . $id_cliente . "' WHERE id_enc_mecanica='" . $id_registro . "'";
	$query_update = mysqli_query($con, $sql);
	if ($query_update) {
		echo "<script>
		$.notify('Los datos para la facturación han sido actualizados.','success');
		</script>";
		echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";
	} else {
		echo "<script>
		$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
		</script>";
	}
}
?>
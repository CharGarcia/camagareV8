<?php
include("../validadores/generador_codigo_unico.php");
include("../clases/asientos_contables.php");
include("../helpers/helpers.php");
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();

$ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
$id_usuario  = isset($_SESSION['id_usuario'])  ? $_SESSION['id_usuario']  : '';

$errors = array();
$messages = array();

// 1) Validaciones base
if (empty($_POST['fecha_diario'])) {
	$errors[] = "Ingrese fecha del diario.";
} else if (!strtotime($_POST['fecha_diario'])) {
	$errors[] = "Ingrese una fecha correcta.";
} else if (periodosContables($con, $_POST['fecha_diario'], $ruc_empresa) === true) {
	$errors[] = "El período contable " . date("m-Y", strtotime($_POST['fecha_diario'])) . " se encuentra cerrado para registrar transacciones.";
} else if (empty($_POST['concepto_diario'])) {
	$errors[] = "Ingrese un concepto general relacionado al registro.";
} else {
	// Comparación numérica con tolerancia (evita problemas por decimales tipo '10' vs '10.00')
	$debe  = isset($_POST['subtotal_debe'])  ? floatval($_POST['subtotal_debe'])  : 0.0;
	$haber = isset($_POST['subtotal_haber']) ? floatval($_POST['subtotal_haber']) : 0.0;
	if (abs($debe - $haber) > 0.0001) {
		$errors[] = "El asiento no cumple con partida doble.";
	}
}

if (!empty($errors)) {
?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php foreach ($errors as $error) {
			echo $error;
		} ?>
	</div>
<?php
	exit;
}

// 2) Normaliza datos
$codigo_unico     = isset($_POST["codigo_unico"]) ? $_POST["codigo_unico"] : "";
$fecha_diario     = date('Y-m-d H:i:s', strtotime(mysqli_real_escape_string($con, strip_tags($_POST["fecha_diario"], ENT_QUOTES))));
$concepto_diario  = mysqli_real_escape_string($con, strip_tags($_POST["concepto_diario"], ENT_QUOTES));

// 3) Candado de concurrencia (idempotencia por ventana corta)
//    Evita que 2-3 requests simultáneos editen el mismo asiento.
$lockKey = "edita_asiento:" . $codigo_unico; // para nuevos, igual sirve (queda vacío, pero no compite)
$lockKeySQL = mysqli_real_escape_string($con, $lockKey);
$rsLock = mysqli_query($con, "SELECT GET_LOCK('$lockKeySQL', 5) AS lck");
$gotLock = 0;
if ($rsLock) {
	$rowLock = mysqli_fetch_assoc($rsLock);
	$gotLock = isset($rowLock['lck']) ? intval($rowLock['lck']) : 0;
}
if ($gotLock !== 1) {
	// No se pudo tomar el candado en 5s; probablemente otro proceso ya está editando
	echo "<script>$.notify('Otra edición está en curso. Inténtalo de nuevo en unos segundos.','warn');</script>";
	exit;
}

$asiento_contable = new asientos_contables();

if ($codigo_unico !== "") {
	// ===== EDITAR =====
	// Usa ruc_empresa exacto (evita el MID(...) que puede mezclar compañías)
	$sql_diario_temporal = mysqli_query(
		$con,
		"SELECT 1 FROM detalle_diario_tmp 
         WHERE id_usuario = '" . mysqli_real_escape_string($con, $id_usuario) . "' 
           AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' 
         LIMIT 1"
	);
	if (!$sql_diario_temporal || mysqli_num_rows($sql_diario_temporal) == 0) {
		echo "<script>$.notify('No hay detalle de cuentas agregados al asiento.','error');</script>";
		// Libera lock
		mysqli_query($con, "SELECT RELEASE_LOCK('$lockKeySQL')");
		exit;
	}

	// Llama a la versión con transacción + DELETE previo del detalle
	$edita_asiento = $asiento_contable->edita_asiento($con, $fecha_diario, $concepto_diario, $ruc_empresa, $id_usuario, $codigo_unico);

	// Libera lock
	mysqli_query($con, "SELECT RELEASE_LOCK('$lockKeySQL')");

	if ($edita_asiento === '1') {
		echo "<script>$.notify('Asiento editado con éxito.','success');</script>";
		exit;
	} else {
		echo "<script>$.notify('Lo siento, algo ha salido mal. Intenta nuevamente.','error');</script>";
		exit;
	}
} else {
	// ===== NUEVO =====
	$sql_diario_temporal = mysqli_query(
		$con,
		"SELECT 1 FROM detalle_diario_tmp 
         WHERE id_usuario = '" . mysqli_real_escape_string($con, $id_usuario) . "' 
           AND ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' 
         LIMIT 1"
	);
	if (!$sql_diario_temporal || mysqli_num_rows($sql_diario_temporal) == 0) {
		echo "<script>$.notify('No hay detalle de cuentas agregados al asiento.','error');</script>";
		mysqli_query($con, "SELECT RELEASE_LOCK('$lockKeySQL')");
		exit;
	}

	$guarda_asiento = $asiento_contable->guarda_asiento($con, $fecha_diario, $concepto_diario, 'DIARIO', '0', $ruc_empresa, $id_usuario, '0');

	// Libera lock
	mysqli_query($con, "SELECT RELEASE_LOCK('$lockKeySQL')");

	if ($guarda_asiento === '1') {
		echo "<script>
            $.notify('Asiento guardado con éxito.','success');
            setTimeout(function (){location.href ='../modulos/libro_diario.php'}, 1000);
        </script>";
		exit;
	}
	if ($guarda_asiento === '2') {
		echo "<script>$.notify('El concepto ya está registrado en otro asiento.','error');</script>";
		exit;
	}
	if ($guarda_asiento === '3') {
		echo "<script>$.notify('Uno o más detalles del asiento ya están registrados en otros asientos.','error');</script>";
		exit;
	}

	echo "<script>$.notify('No se pudo guardar el asiento.','error');</script>";
	exit;
}

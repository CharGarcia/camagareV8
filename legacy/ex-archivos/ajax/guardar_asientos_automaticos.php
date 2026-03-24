<?php
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'guardar_asientos' and isset($_POST['tipo_asiento'])) {
	$tipo_asiento = $_POST['tipo_asiento'];
	$fecha_desde = date('Y-m-d', strtotime($_POST['fecha_desde'], ENT_QUOTES));
	$fecha_hasta = date('Y-m-d', strtotime($_POST['fecha_hasta'], ENT_QUOTES));
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];

	$sql_diario_temporal = mysqli_query($con, "SELECT * FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$count = mysqli_num_rows($sql_diario_temporal);
	if ($count == 0) {
		echo "<script>
				$.notify('No hay documentos para contabilizar.','error');
				</script>";
		exit;
	}

	$sql_faltantes = mysqli_query($con, "SELECT * FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_cuenta ='0'");
	$count_faltantes = mysqli_num_rows($sql_faltantes);
	if ($count_faltantes > 0) {
		echo "<script>
			$.notify('Revise el mensaje en la pestaña asientos contables, luego configurar cuentas en configuración contable','error');
			</script>";
		exit;
	}

	$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, $tipo_asiento);
	if ($guardar_asientos_contables_generados == 'partidaDoble') {
		echo "<script>
			$.notify('Existen asientos que no cumplen con partida doble.','error');
			</script>";
		exit;
	}


	echo "<script>
				$.notify('Registros guardados con éxito','success');
				setTimeout(function (){location.href ='../modulos/generar_asientos.php'}, 1000);
				</script>";
} else {
	echo "<script>
		$.notify('Error desconocido','error');
		setTimeout(function (){location.href ='../modulos/generar_asientos.php'}, 1000);
		</script>";
}



